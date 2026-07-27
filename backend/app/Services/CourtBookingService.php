<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\CourtBooking;
use App\Models\CourtBookingLock;
use App\Models\CourtBookingStatusHistory;
use App\Models\CourtMaintenance;
use App\Mail\CourtBookingCreatedMail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;

class CourtBookingService
{
    /**
     * Lock a court slot temporarily to prevent race conditions.
     */
    public function lockSlot(array $data)
    {
        $courtId = $data['court_id'];
        $date = $data['booking_date'];
        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->normalizeTime($data['end_time']);

        $lockName = $this->slotLockName($courtId, $date);
        if (!$this->acquireAdvisoryLock($lockName)) {
            throw new Exception('Hệ thống đang bận, vui lòng thử lại sau giây lát.');
        }

        try {
            return DB::transaction(function () use ($data, $courtId, $date, $startTime, $endTime) {
                $userId = auth()->guard('api')->id();

            if (!$userId) {
                throw new Exception('Vui lòng đăng nhập bằng tài khoản khách hàng để giữ chỗ.');
            }

            // 1. Check existing booking overlap (so sánh cột thô để tận dụng index)
            $conflict = DB::table('court_bookings')
                ->where('court_id', $courtId)
                ->where('booking_date', $date)
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new Exception('Sân đã được đặt trong khung giờ này.');
            }

            // 1.5 Xóa các lock cũ của CHÍNH user này trên cùng sân, cùng ngày
            // Việc này giúp frontend không cần gọi API release-lock thủ công, giảm 50% API calls
            // và bảo toàn lock cũ nếu API bị lỗi (VD: 429 Rate Limit) vì transaction sẽ rollback/không chạy.
            DB::table('court_booking_locks')
                ->where('user_id', $userId)
                ->where('court_id', $courtId)
                ->where('booking_date', $date)
                ->delete();

            // 2. Check existing active lock (của người khác)
            $lockConflict = DB::table('court_booking_locks')
                ->where('court_id', $courtId)
                ->where('booking_date', $date)
                ->where('expires_at', '>', now())
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($lockConflict) {
                throw new Exception('Slot đang được giữ tạm bởi người khác. Vui lòng thử lại sau.');
            }

            // 3. Check maintenance
            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $courtId)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "$date $endTime")
                ->where('end_datetime', '>', "$date $startTime")
                ->exists();

            if ($maintenanceConflict) {
                throw new Exception('Sân đang trong lịch bảo trì tại khung giờ này.');
            }

            // 4. Create Lock
            $lock = CourtBookingLock::create([
                'court_id' => $courtId,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'user_id' => $userId,
                'lock_token' => (string) \Illuminate\Support\Str::uuid(),
                'expires_at' => now()->addMinutes(5),
            ]);

            app(CourtBookingWorkflowService::class)->logActivity('booking.lock.created', $lock, null, $lock->toArray(), 'user', $userId, request());
            \App\Events\CourtBookingRealtimeEvent::dispatch('CourtSlotLocked', [
                'court_id' => $courtId,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'expires_at' => $lock->expires_at?->toDateTimeString(),
            ]);

            return $lock;
            });
        } finally {
            $this->releaseAdvisoryLock($lockName);
        }
    }

    public function releaseLock(string $lockToken, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($lockToken, $userId) {
            $lock = CourtBookingLock::where('lock_token', $lockToken)
                ->when($userId, fn ($query) => $query->where('user_id', $userId))
                ->lockForUpdate()
                ->first();

            if (!$lock) {
                return false;
            }

            $payload = [
                'court_id' => $lock->court_id,
                'booking_date' => $lock->booking_date->format('Y-m-d'),
                'start_time' => $lock->start_time,
                'end_time' => $lock->end_time,
            ];

            $lock->delete();

            app(CourtBookingWorkflowService::class)->logActivity('booking.lock.released', $lock, $payload, null, 'user', $userId, request());
            \App\Events\CourtBookingRealtimeEvent::dispatch('CourtSlotReleased', $payload);

            return true;
        });
    }

    /**
     * Create a court booking officially.
     */
    public function createBooking(array $data): CourtBooking
    {
        $courtId = $data['court_id'];
        $date = $data['booking_date'];
        $startTime = $this->normalizeTime($data['start_time']);
        $endTime = $this->normalizeTime($data['end_time']);

        // Serialize mọi lượt đặt cùng 1 sân + ngày để chống double-booking.
        // Dùng GET_LOCK (advisory lock) thay vì dựa vào gap-lock của InnoDB — gap-lock
        // bị vô hiệu dưới READ COMMITTED, còn whereDate/whereTime lại vô hiệu hóa index.
        $lockName = $this->slotLockName($courtId, $date);
        if (!$this->acquireAdvisoryLock($lockName)) {
            throw new Exception('Hệ thống đang bận, vui lòng thử lại sau giây lát.');
        }

        try {
            return DB::transaction(function () use ($data, $courtId, $date, $startTime, $endTime) {
                $userId = auth()->guard('api')->id();

                if (!$userId) {
                    throw new Exception('Vui lòng đăng nhập bằng tài khoản khách hàng để đặt sân.');
                }

                // 1. Check overlap (so sánh cột thô để tận dụng index, không bọc DATE()/TIME())
                $conflict = DB::table('court_bookings')
                    ->where('court_id', $courtId)
                    ->where('booking_date', $date)
                    ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new Exception('Sân đã được đặt trong khung giờ này.');
                }

                // 2. If no lock token provided, check for active locks from others
                if (empty($data['lock_token'])) {
                    $lockConflict = DB::table('court_booking_locks')
                        ->where('court_id', $courtId)
                        ->where('booking_date', $date)
                        ->where('expires_at', '>', now())
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime)
                        ->lockForUpdate()
                        ->exists();

                    if ($lockConflict) {
                        throw new Exception('Slot đang được giữ tạm bởi người khác. Vui lòng thử lại sau.');
                    }
                } else {
                    // Verify lock token
                    $lock = CourtBookingLock::where('lock_token', $data['lock_token'])
                        ->where('court_id', $courtId)
                        ->where('booking_date', $date)
                        ->where('start_time', $startTime)
                        ->where('end_time', $endTime)
                        ->where('user_id', $userId)
                        ->where('expires_at', '>', now())
                        ->lockForUpdate()
                        ->first();
                    if (!$lock) {
                        throw new Exception('Lock token không hợp lệ hoặc đã hết hạn.');
                    }
                }

            // 3. Maintenance check
            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $courtId)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "$date $endTime")
                ->where('end_datetime', '>', "$date $startTime")
                ->exists();

            if ($maintenanceConflict) {
                throw new Exception('Sân đang trong lịch bảo trì tại khung giờ này.');
            }

            // calculate duration
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            $durationMinutes = $start->diffInMinutes($end);

            // Calculate real price based on `court_prices`
            $dayOfWeek = Carbon::parse($date)->dayOfWeek; // 0 (Sunday) - 6 (Saturday)
            $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
            $dayType = $isWeekend ? 'weekend' : 'weekday';
            
            // Tính giá theo TỪNG block giờ chồng lấn (blended) — booking bắc qua ranh giới
            // cao điểm/thấp điểm sẽ được tính đúng theo số phút thuộc mỗi block.
            $originalPrice = $this->priceForRange($courtId, $dayType, $startTime, $endTime);
            $serviceItems = collect($data['services'] ?? []);
            $serviceAmount = 0;
            $serviceSnapshots = [];

            if ($serviceItems->isNotEmpty()) {
                $services = \App\Models\CourtService::whereIn('service_id', $serviceItems->pluck('service_id'))
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('service_id');

                foreach ($serviceItems as $item) {
                    $service = $services->get($item['service_id']);
                    if (!$service) {
                        throw new Exception('Dịch vụ chọn thêm không hợp lệ hoặc đã ngừng bán.');
                    }

                    $quantity = (int) $item['quantity'];
                    $subtotal = (int) $service->unit_price * $quantity;
                    $serviceAmount += $subtotal;
                    $serviceSnapshots[] = [
                        'service_id' => $service->service_id,
                        'quantity' => $quantity,
                        'unit_price' => (int) $service->unit_price,
                        'subtotal' => $subtotal,
                    ];
                }
            }

            $bookingCode = 'BK-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(4));

            // 4. Create booking
            $booking = CourtBooking::create([
                'booking_code' => $bookingCode,
                'user_id' => $userId,
                'court_id' => $courtId,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'original_price' => $originalPrice,
                'service_amount' => $serviceAmount,
                'total_amount' => $originalPrice + $serviceAmount,
                'payment_method' => $data['payment_method'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            foreach ($serviceSnapshots as $serviceSnapshot) {
                \App\Models\CourtBookingService::create([
                    'booking_id' => $booking->booking_id,
                    ...$serviceSnapshot,
                ]);
            }

            // 5. Create History
            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => null,
                'new_status' => 'pending',
                'actor_type' => auth()->guard('api')->check() ? 'user' : 'admin',
                'actor_id' => auth()->guard('api')->id() ?? auth()->guard('admin')->id(),
            ]);

            // 6. Delete lock if exists
            if (!empty($data['lock_token'])) {
                CourtBookingLock::where('lock_token', $data['lock_token'])
                    ->where('user_id', $userId)
                    ->delete();
            }

            // logActivity là audit log → giữ trong transaction để atomic với booking.
            app(CourtBookingWorkflowService::class)->logActivity('booking.created', $booking, null, $booking->toArray(), 'user', $userId, request());

            // Các side-effect (broadcast realtime, notification, email) chỉ chạy SAU khi
            // transaction commit thành công — tránh gửi thông báo cho booking bị rollback,
            // và tránh worker đọc phải row chưa commit khi queue driver = sync.
            DB::afterCommit(function () use ($booking) {
                $workflow = app(CourtBookingWorkflowService::class);
                $workflow->broadcast('CourtBookingCreated', $booking);
                $workflow->notifyUser($booking, 'CourtBookingCreated');
                $workflow->notifyAdmins($booking, 'created');

                if ($booking->user?->email) {
                    try {
                        Mail::to($booking->user->email)->queue(new CourtBookingCreatedMail($booking));
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Failed to queue booking created mail', [
                            'booking_id' => $booking->booking_id,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }
            });

                return $booking;
            });
        } finally {
            // Luôn giải phóng advisory lock dù transaction thành công hay lỗi.
            $this->releaseAdvisoryLock($lockName);
        }
    }

    /**
     * Tên advisory lock để serialize đặt sân theo court + ngày.
     * Giới hạn 64 ký tự của MySQL GET_LOCK.
     */
    private function slotLockName(int|string $courtId, string $date): string
    {
        return 'court_booking:' . $courtId . ':' . $date;
    }

    private function acquireAdvisoryLock(string $lockName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return true;
        }

        $gotLock = DB::selectOne('SELECT GET_LOCK(?, 10) AS ok', [$lockName]);
        return $gotLock && (int) $gotLock->ok === 1;
    }

    private function releaseAdvisoryLock(string $lockName): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /**
     * Tính giá đặt sân theo từng block giờ chồng lấn (blended pricing).
     *
     * Cộng dồn giá theo số phút thực thuộc mỗi block giá. Xử lý đúng trường hợp
     * booking bắc qua ranh giới cao điểm/thấp điểm. Fail-closed: nếu không có block
     * giá nào bao phủ khoảng thời gian thì ném lỗi thay vì đoán một mức giá magic.
     *
     * @throws Exception nếu chưa cấu hình giá cho khung giờ.
     */
    private function priceForRange(int|string $courtId, string $dayType, string $startTime, string $endTime): int
    {
        $blocks = \App\Models\CourtPrice::where('court_id', $courtId)
            ->where('is_active', true)
            ->where(function ($q) use ($dayType) {
                $q->where('day_type', $dayType)->orWhere('day_type', 'all');
            })
            ->where('from_time', '<', $endTime)
            ->where('to_time', '>', $startTime)
            ->orderBy('from_time')
            ->get();

        if ($blocks->isEmpty()) {
            throw new Exception('Chưa cấu hình giá cho khung giờ này. Vui lòng liên hệ quản trị viên.');
        }

        $rangeStart = strtotime($startTime);
        $rangeEnd   = strtotime($endTime);
        $total      = 0.0;
        $coveredMinutes = 0;

        // Ưu tiên block cụ thể (weekday/weekend/holiday) hơn 'all' khi trùng khoảng:
        // sắp xếp để block khớp day_type cụ thể đứng trước, tránh tính chồng.
        $ordered = $blocks->sortBy(fn ($b) => $b->day_type === 'all' ? 1 : 0)->values();
        $claimed = []; // các phút đã được tính (chống overlap giữa các block)

        foreach ($ordered as $block) {
            $segStart = max($rangeStart, strtotime($block->from_time));
            $segEnd   = min($rangeEnd, strtotime($block->to_time));

            if ($segEnd <= $segStart) {
                continue;
            }

            // Trừ đi phần phút đã bị block trước chiếm để không tính hai lần.
            $minutes = 0;
            for ($t = $segStart; $t < $segEnd; $t += 60) {
                if (!isset($claimed[$t])) {
                    $claimed[$t] = true;
                    $minutes++;
                }
            }

            if ($minutes > 0) {
                $total += ((float) $block->price_per_hour / 60) * $minutes;
                $coveredMinutes += $minutes;
            }
        }

        $requestedMinutes = (int) round(($rangeEnd - $rangeStart) / 60);
        if ($coveredMinutes < $requestedMinutes) {
            throw new Exception('Khung giờ đặt sân nằm ngoài bảng giá đã cấu hình.');
        }

        return (int) round($total);
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::createFromFormat(strlen($time) === 5 ? 'H:i' : 'H:i:s', $time)->format('H:i:s');
    }
}
