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
        return DB::transaction(function () use ($data) {
            $courtId = $data['court_id'];
            $date = $data['booking_date'];
            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = $this->normalizeTime($data['end_time']);
            $userId = auth()->guard('api')->id();

            if (!$userId) {
                throw new Exception('Vui lòng đăng nhập bằng tài khoản khách hàng để giữ chỗ.');
            }

            // 1. Check existing booking overlap
            $conflict = DB::table('court_bookings')
                ->where('court_id', $courtId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->whereTime('start_time', '<', $endTime)
                ->whereTime('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new Exception('Sân đã được đặt trong khung giờ này.');
            }

            // 2. Check existing active lock
            $lockConflict = DB::table('court_booking_locks')
                ->where('court_id', $courtId)
                ->whereDate('booking_date', $date)
                ->where('expires_at', '>', now())
                ->whereTime('start_time', '<', $endTime)
                ->whereTime('end_time', '>', $startTime)
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
        return DB::transaction(function () use ($data) {
            $courtId = $data['court_id'];
            $date = $data['booking_date'];
            $startTime = $this->normalizeTime($data['start_time']);
            $endTime = $this->normalizeTime($data['end_time']);
            $userId = auth()->guard('api')->id();

            if (!$userId) {
                throw new Exception('Vui lòng đăng nhập bằng tài khoản khách hàng để đặt sân.');
            }

            // 1. Check overlap again (in case lock expired or user didn't use lock)
            $conflict = DB::table('court_bookings')
                ->where('court_id', $courtId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->whereTime('start_time', '<', $endTime)
                ->whereTime('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new Exception('Sân đã được đặt trong khung giờ này.');
            }

            // 2. If no lock token provided, check for active locks from others
            if (empty($data['lock_token'])) {
                $lockConflict = DB::table('court_booking_locks')
                    ->where('court_id', $courtId)
                    ->whereDate('booking_date', $date)
                    ->where('expires_at', '>', now())
                    ->whereTime('start_time', '<', $endTime)
                    ->whereTime('end_time', '>', $startTime)
                    ->lockForUpdate()
                    ->exists();

                if ($lockConflict) {
                    throw new Exception('Slot đang được giữ tạm bởi người khác. Vui lòng thử lại sau.');
                }
            } else {
                // Verify lock token
                $lock = CourtBookingLock::where('lock_token', $data['lock_token'])
                    ->where('court_id', $courtId)
                    ->whereDate('booking_date', $date)
                    ->whereTime('start_time', $startTime)
                    ->whereTime('end_time', $endTime)
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
            
            $courtPrice = \App\Models\CourtPrice::where('court_id', $courtId)
                ->where('is_active', true)
                ->where(function($q) use ($dayType) {
                    $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                })
                ->where('from_time', '<=', $startTime)
                ->where('to_time', '>=', $endTime)
                ->first();

            $pricePerHour = $courtPrice ? $courtPrice->price_per_hour : 100000; // Default fallback
            $originalPrice = (int) round(($pricePerHour / 60) * $durationMinutes);
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

            app(CourtBookingWorkflowService::class)->logActivity('booking.created', $booking, null, $booking->toArray(), 'user', $userId, request());
            app(CourtBookingWorkflowService::class)->broadcast('CourtBookingCreated', $booking);
            app(CourtBookingWorkflowService::class)->notifyUser($booking, 'CourtBookingCreated');
            app(CourtBookingWorkflowService::class)->notifyAdmins($booking, 'created');

            // Gửi email xác nhận đặt sân cho khách (qua queue để không block response)
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

            return $booking;
        });
    }

    private function normalizeTime(string $time): string
    {
        return Carbon::createFromFormat(strlen($time) === 5 ? 'H:i' : 'H:i:s', $time)->format('H:i:s');
    }
}
