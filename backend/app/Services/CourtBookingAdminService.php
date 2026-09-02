<?php

namespace App\Services;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\CourtBookingExtension;
use App\Models\CourtBookingPayment;
use App\Models\CourtBookingService as CourtBookingServiceModel;
use App\Models\CourtBookingStatusHistory;
use App\Models\CourtPrice;
use App\Models\CourtSchedule;
use App\Models\CourtService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CourtBookingAdminService — logic nghiệp vụ đặt sân phía admin/lễ tân.
 *
 * Tách khỏi controller (vốn 1085 dòng, 128 DB call). Các workflow chuyển trạng thái
 * (confirm/check-in/cancel/qr/payment) vẫn do CourtBookingWorkflowService đảm nhiệm;
 * service này lo phần tạo booking POS, check-out, dịch vụ, gia hạn, dashboard, thống kê.
 *
 * Các method mutate trả mảng chuẩn {ok, code, message?, data?} để controller map
 * đúng HTTP status (đặc biệt 409 conflict) — giữ nguyên response shape cũ.
 */
class CourtBookingAdminService
{
    private const DEFAULT_PRICE_PER_HOUR = 100000;

    public function __construct(
        private CourtBookingWorkflowService $workflowService
    ) {}

    /**
     * Danh sách booking có filter + phân trang.
     */
    public function paginate(Request $request)
    {
        $query = CourtBooking::with(['user', 'court', 'services.service', 'payments', 'extensions'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc');

        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->date);
        }
        if ($request->filled('court_id')) {
            $query->where('court_id', $request->court_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('booking_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('booking_date', '<=', $request->to_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%$search%")
                    ->orWhere('customer_name', 'like', "%$search%")
                    ->orWhere('customer_phone', 'like', "%$search%")
                    ->orWhere('customer_email', 'like', "%$search%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('full_name', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
            });
        }

        return $query->paginate($request->per_page ?? 20);
    }

    /**
     * Tạo booking cho khách vãng lai (POS / walk-in).
     *
     * @return array{ok: bool, code: int, message?: string, data?: CourtBooking}
     */
    public function createWalkIn(array $validated, ?int $staffId, Request $request): array
    {
        return DB::transaction(function () use ($validated, $staffId, $request) {
            $startTime = $validated['start_time'].':00';
            $endTime = $validated['end_time'].':00';

            // Check overlap với booking khác (khóa để chống race)
            $conflict = CourtBooking::where('court_id', $validated['court_id'])
                ->where('booking_date', $validated['booking_date'])
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return ['ok' => false, 'code' => 409, 'message' => 'Sân đã được đặt trong khung giờ này.'];
            }

            $lockConflict = DB::table('court_booking_locks')
                ->where('court_id', $validated['court_id'])
                ->where('booking_date', $validated['booking_date'])
                ->where('expires_at', '>', now())
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($lockConflict) {
                return ['ok' => false, 'code' => 409, 'message' => 'Khung giờ này đang được khách giữ chỗ tạm thời.'];
            }

            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $validated['court_id'])
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "{$validated['booking_date']} $endTime")
                ->where('end_datetime', '>', "{$validated['booking_date']} $startTime")
                ->exists();

            if ($maintenanceConflict) {
                return ['ok' => false, 'code' => 409, 'message' => 'Sân đang bảo trì trong khung giờ này.'];
            }

            $durationMinutes = Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
            $originalPrice = $this->calcPrice($validated['court_id'], $validated['booking_date'], $startTime, $endTime, $durationMinutes);

            $bookingCode = 'BK-'.now()->format('Ymd').'-'.str_pad(
                CourtBooking::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $booking = CourtBooking::create([
                'booking_code' => $bookingCode,
                'court_id' => $validated['court_id'],
                'user_id' => $validated['user_id'] ?? null,
                'staff_id' => $staffId,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'booking_date' => $validated['booking_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'status' => 'confirmed',
                'original_price' => $originalPrice,
                'total_amount' => $originalPrice,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'payment_status' => 'unpaid',
                'confirmed_at' => now(),
                'source' => 'pos',
                'note' => $validated['note'] ?? null,
            ]);

            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => null,
                'new_status' => 'confirmed',
                'note' => 'Đặt sân tại quầy (POS)',
                'actor_type' => 'admin',
                'actor_id' => $staffId,
            ]);

            $this->workflowService->logActivity('booking.pos.created', $booking, null, $booking->toArray(), 'admin', $staffId, $request);
            $this->workflowService->broadcast('CourtBookingCreated', $booking, ['source' => 'pos']);

            return ['ok' => true, 'code' => 201, 'data' => $booking->load(['user', 'court'])];
        });
    }

    /**
     * Quét QR code toàn cục và thực hiện check-in nhận sân tức thì.
     *
     * Hỗ trợ format:
     * - OSBK:{booking_code}:{qr_token}
     * - {"booking_code":"BK-...","qr_token":"..."}
     * - Mã booking BK-...
     * - Chuỗi raw qr_token
     *
     * @return array{ok: bool, code: int, message?: string, data?: CourtBooking}
     */
    public function scanAndCheckIn(string $qrData, ?int $staffId, Request $request, bool $allowOverride = true): array
    {
        $qrData = trim($qrData);
        $bookingCode = null;
        $qrToken = null;

        // 1. Phân tích cú pháp QR
        if (str_starts_with($qrData, 'OSBK:')) {
            $parts = explode(':', $qrData);
            $bookingCode = $parts[1] ?? null;
            $qrToken = $parts[2] ?? null;
        } elseif (str_starts_with($qrData, '{') && str_ends_with($qrData, '}')) {
            $decoded = json_decode($qrData, true);
            $bookingCode = $decoded['booking_code'] ?? null;
            $qrToken = $decoded['qr_token'] ?? null;
        } elseif (str_starts_with($qrData, 'BK-')) {
            $bookingCode = $qrData;
        } else {
            $qrToken = $qrData;
        }

        // 2. Tìm booking
        $booking = null;
        if ($bookingCode) {
            $booking = CourtBooking::with(['user', 'court', 'services.service', 'payments'])->where('booking_code', $bookingCode)->first();
        }

        if (! $booking && $qrToken) {
            // Thử tìm booking có cùng ngày hôm nay / ngày mai để so khớp token
            $candidates = CourtBooking::with(['user', 'court', 'services.service', 'payments'])
                ->whereDate('booking_date', '>=', today()->subDay())
                ->whereDate('booking_date', '<=', today()->addDay())
                ->get();
            foreach ($candidates as $candidate) {
                if (hash_equals($this->workflowService->qrToken($candidate), $qrToken)) {
                    $booking = $candidate;
                    break;
                }
            }
        }

        if (! $booking) {
            return ['ok' => false, 'code' => 404, 'message' => 'Không tìm thấy lịch đặt sân tương ứng với mã QR này.'];
        }

        // 3. Xác thực Token nếu có
        if ($qrToken) {
            try {
                $this->workflowService->assertValidQrToken($booking, $qrToken);
            } catch (\InvalidArgumentException $e) {
                return ['ok' => false, 'code' => 400, 'message' => 'Mã QR không hợp lệ hoặc đã bị chỉnh sửa.'];
            }
        }

        // 4. Kiểm tra trạng thái có thể check-in
        if (in_array($booking->status, ['checked_in', 'playing'], true)) {
            return [
                'ok' => true,
                'code' => 200,
                'message' => 'Khách hàng này đã check-in trước đó rồi.',
                'data' => $booking->load(['user', 'court', 'services.service', 'payments']),
            ];
        }

        if ($booking->status === 'completed') {
            return ['ok' => false, 'code' => 400, 'message' => 'Lịch đặt sân này đã hoàn thành.'];
        }

        if ($booking->status === 'cancelled') {
            return ['ok' => false, 'code' => 400, 'message' => 'Lịch đặt sân này đã bị hủy, không thể check-in.'];
        }

        // 5. Kiểm tra thời gian check-in
        try {
            $this->workflowService->assertCheckInWindow($booking, $allowOverride);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'code' => 400, 'message' => $e->getMessage()];
        }

        // 6. Thực hiện chuyển trạng thái
        try {
            $booking = $this->workflowService->transition(
                $booking,
                'checked_in',
                'admin',
                $staffId,
                'QR Camera Check-in nhanh tại quầy',
                ['checked_in_at' => now()],
                $request
            );

            return [
                'ok' => true,
                'code' => 200,
                'message' => 'Check-in nhận sân thành công!',
                'data' => $booking->load(['user', 'court', 'services.service', 'payments']),
            ];
        } catch (\Exception $e) {
            return ['ok' => false, 'code' => 400, 'message' => 'Check-in thất bại: '.$e->getMessage()];
        }
    }

    /**
     * Check-out trả sân + tự động thu nốt tiền còn thiếu.
     *
     * @return array{ok: bool, code: int, message?: string, amount_due?: int, data?: CourtBooking}
     */
    public function checkOut(int $id, array $validated, ?int $staffId, Request $request): array
    {
        $booking = CourtBooking::with(['services', 'extensions'])->findOrFail($id);

        if (! in_array($booking->status, ['checked_in', 'playing', 'extended'])) {
            return ['ok' => false, 'code' => 400, 'message' => 'Booking phải ở trạng thái "Đang chơi" để check-out.'];
        }

        $remaining = (int) $booking->total_amount - (int) $booking->paid_amount;
        if ($remaining > 0 && empty($validated['payment_method'])) {
            return [
                'ok' => false,
                'code' => 422,
                'message' => 'Booking con tien chua thanh toan. Vui long chon phuong thuc thu tien truoc khi check-out.',
                'amount_due' => $remaining,
            ];
        }

        return DB::transaction(function () use ($booking, $validated, $staffId, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $oldStatus = $booking->status;
            $remaining = (int) $booking->total_amount - (int) $booking->paid_amount;

            if ($remaining > 0) {
                CourtBookingPayment::create([
                    'booking_id' => $booking->booking_id,
                    'payment_type' => 'full',
                    'payment_method' => $validated['payment_method'],
                    'transaction_code' => $validated['transaction_code'] ?? 'CHECKOUT-'.$booking->booking_code.'-'.now()->format('His'),
                    'amount' => $remaining,
                    'status' => 'success',
                    'paid_at' => now(),
                    'note' => 'Thanh toán khi check-out',
                    'processed_by' => $staffId,
                ]);
                $booking->paid_amount = $booking->total_amount;
                $booking->payment_method = $validated['payment_method'];
            }

            $booking->status = 'completed';
            $booking->checked_out_at = now();
            $booking->payment_status = 'paid';
            $booking->save();

            $this->workflowService->logActivity(
                'booking.completed',
                $booking,
                ['status' => $oldStatus],
                ['status' => 'completed', 'paid_amount' => $booking->paid_amount],
                'admin',
                $staffId,
                $request
            );
            $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, [
                'old_status' => $oldStatus,
                'new_status' => 'completed',
            ]);

            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => $oldStatus,
                'new_status' => 'completed',
                'note' => 'Check-out trả sân & thanh toán',
                'actor_type' => 'admin',
                'actor_id' => $staffId,
            ]);

            return ['ok' => true, 'code' => 200, 'data' => $booking->load(['payments'])];
        });
    }

    /**
     * Thêm dịch vụ vào booking.
     *
     * @return array{ok: bool, code: int, data?: CourtBookingServiceModel}
     */
    public function addService(int $id, array $validated, ?int $staffId, Request $request): array
    {
        $booking = CourtBooking::findOrFail($id);

        return DB::transaction(function () use ($booking, $validated, $staffId, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $service = CourtService::findOrFail($validated['service_id']);
            $subtotal = $service->unit_price * $validated['quantity'];

            $bookingService = CourtBookingServiceModel::create([
                'booking_id' => $booking->booking_id,
                'service_id' => $service->service_id,
                'quantity' => $validated['quantity'],
                'unit_price' => $service->unit_price,
                'subtotal' => $subtotal,
                'note' => $validated['note'] ?? null,
                'added_by' => $staffId,
            ]);

            $booking->service_amount += $subtotal;
            $booking->total_amount += $subtotal;
            $booking->save();

            $this->workflowService->logActivity('booking.service.added', $booking, null, $bookingService->toArray(), 'admin', $staffId, $request);
            $this->workflowService->broadcast('CourtBookingServiceAdded', $booking, ['service_amount' => $booking->service_amount, 'total_amount' => $booking->total_amount]);

            return ['ok' => true, 'code' => 200, 'data' => $bookingService->load('service')];
        });
    }

    /**
     * Gia hạn giờ chơi.
     *
     * @return array{ok: bool, code: int, message?: string, data?: CourtBookingExtension}
     */
    public function extend(int $id, array $validated, ?int $staffId, Request $request): array
    {
        $booking = CourtBooking::findOrFail($id);

        return DB::transaction(function () use ($booking, $validated, $staffId, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $currentEnd = Carbon::parse($booking->end_time);
            $newEnd = $currentEnd->copy()->addMinutes($validated['extension_minutes']);

            // Chặn gia hạn vượt qua nửa đêm: end_time là cột TIME, cộng phút sang ngày mới
            // khiến so sánh overlap theo TIME sai. Không cho phép.
            if ($newEnd->format('Y-m-d') !== $currentEnd->format('Y-m-d')) {
                return ['ok' => false, 'code' => 400, 'message' => 'Không thể gia hạn qua nửa đêm. Vui lòng tạo booking mới cho ngày hôm sau.'];
            }

            $newEndTime = $newEnd->format('H:i:s');

            // Chặn gia hạn vượt giờ đóng cửa (nếu có lịch mở cửa).
            $schedule = CourtSchedule::where('court_id', $booking->court_id)
                ->where('day_of_week', $booking->booking_date->dayOfWeek)
                ->where('is_active', true)
                ->first();
            if ($schedule && $schedule->close_time && $newEndTime > Carbon::parse($schedule->close_time)->format('H:i:s')) {
                return ['ok' => false, 'code' => 400, 'message' => 'Không thể gia hạn vượt quá giờ đóng cửa của sân.'];
            }

            $conflict = DB::table('court_bookings')
                ->where('court_id', $booking->court_id)
                ->where('booking_date', $booking->booking_date)
                ->where('booking_id', '!=', $booking->booking_id)
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->where('start_time', '<', $newEndTime)
                ->where('end_time', '>', $booking->end_time)
                ->exists();

            if ($conflict) {
                return ['ok' => false, 'code' => 400, 'message' => 'Không thể gia hạn. Sân đã được đặt cho khung giờ tiếp theo.'];
            }

            $lockConflict = DB::table('court_booking_locks')
                ->where('court_id', $booking->court_id)
                ->where('booking_date', $booking->booking_date)
                ->where('expires_at', '>', now())
                ->where('start_time', '<', $newEndTime)
                ->where('end_time', '>', $booking->end_time)
                ->exists();

            if ($lockConflict) {
                return ['ok' => false, 'code' => 400, 'message' => 'Khong the gia han vi khung gio tiep theo dang duoc giu cho.'];
            }

            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $booking->court_id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "{$booking->booking_date->format('Y-m-d')} $newEndTime")
                ->where('end_datetime', '>', "{$booking->booking_date->format('Y-m-d')} {$booking->end_time}")
                ->exists();

            if ($maintenanceConflict) {
                return ['ok' => false, 'code' => 400, 'message' => 'Khong the gia han vi san co lich bao tri.'];
            }

            $bookingDate = $booking->booking_date instanceof Carbon
                ? $booking->booking_date
                : Carbon::parse($booking->booking_date);
            $dayType = in_array($bookingDate->dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';
            $currentEndTime = $booking->end_time;

            $courtPrice = CourtPrice::where('court_id', $booking->court_id)
                ->where('is_active', true)
                ->where(function ($q) use ($dayType) {
                    $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                })
                ->where('from_time', '<=', $currentEndTime)
                ->where('to_time', '>=', $newEndTime)
                ->first();

            if (! $courtPrice) {
                $courtPrice = CourtPrice::where('court_id', $booking->court_id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($dayType) {
                        $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                    })
                    ->first();
            }

            $pricePerHour = $courtPrice ? (float) $courtPrice->price_per_hour : self::DEFAULT_PRICE_PER_HOUR;
            $extraAmount = (int) round(($pricePerHour / 60) * $validated['extension_minutes']);

            $extension = CourtBookingExtension::create([
                'booking_id' => $booking->booking_id,
                'original_end_time' => $booking->end_time,
                'extended_end_time' => $newEndTime,
                'extension_minutes' => $validated['extension_minutes'],
                'extra_amount' => $extraAmount,
                'approved_by' => $staffId,
            ]);

            $oldStatus = $booking->status;
            $booking->end_time = $newEndTime;
            $booking->duration_minutes += $validated['extension_minutes'];
            $booking->total_amount += $extraAmount;
            $booking->status = 'extended';
            $booking->save();

            $this->workflowService->logActivity('booking.extended', $booking, ['status' => $oldStatus], [
                'status' => 'extended',
                'end_time' => $newEndTime,
                'extension_minutes' => $validated['extension_minutes'],
                'extra_amount' => $extraAmount,
            ], 'admin', $staffId, $request);
            $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'extended']);

            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => $oldStatus,
                'new_status' => 'extended',
                'note' => "Gia hạn thêm {$validated['extension_minutes']} phút",
                'actor_type' => 'admin',
                'actor_id' => $staffId,
            ]);

            return ['ok' => true, 'code' => 200, 'data' => $extension];
        });
    }

    /**
     * Ghi nhận nhiều khoản thanh toán (split payment).
     *
     * @return array{ok: bool, code: int, message?: string, data?: array}
     */
    public function splitPayment(int $id, array $validated, ?int $staffId, Request $request): array
    {
        $booking = CourtBooking::findOrFail($id);
        $recordedPayments = [];

        try {
            DB::transaction(function () use ($booking, $validated, &$recordedPayments, $staffId, $request) {
                foreach ($validated['payments'] as $paymentData) {
                    $recordedPayments[] = $this->workflowService->recordPayment(
                        $booking,
                        $paymentData,
                        'admin',
                        $staffId,
                        $request
                    );

                    if ($paymentData['payment_type'] === 'deposit') {
                        $booking->deposit_amount += $paymentData['amount'];
                    }
                }
                CourtBooking::whereKey($booking->getKey())->update(['deposit_amount' => $booking->deposit_amount]);
            });

            return ['ok' => true, 'code' => 200, 'data' => $recordedPayments];
        } catch (\Exception $e) {
            return ['ok' => false, 'code' => 400, 'message' => $e->getMessage()];
        }
    }

    /**
     * Kiểm tra xung đột khung giờ (dùng khi kéo-thả booking trên lịch).
     */
    public function checkConflicts(array $validated): array
    {
        $query = CourtBooking::where('court_id', $validated['court_id'])
            ->where('booking_date', $validated['booking_date'])
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time']);

        if (! empty($validated['exclude_booking_id'])) {
            $query->where('booking_id', '!=', $validated['exclude_booking_id']);
        }

        return $query->get()->all();
    }

    /**
     * Dữ liệu lịch (day/week/month).
     */
    public function calendar(Request $request): array
    {
        $mode = $request->query('mode', 'day');
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        [$fromDate, $toDate] = match ($mode) {
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $bookings = CourtBooking::with(['court:court_id,court_name,court_code', 'user:user_id,full_name,phone'])
            ->whereBetween('booking_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($request->filled('court_id'), fn ($query) => $query->where('court_id', $request->court_id))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return [
            'mode' => $mode,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'items' => $bookings,
            'by_date' => $bookings->groupBy(fn ($booking) => $booking->booking_date->format('Y-m-d')),
        ];
    }

    /**
     * Dashboard lễ tân — trạng thái realtime các sân + thống kê ngày.
     */
    public function dashboard(Request $request): array
    {
        $date = $request->date ?? today()->toDateString();
        $now = now()->format('H:i:s');

        $courts = Court::with(['schedules'])->orderBy('sort_order')->get();

        $bookingsByCourt = CourtBooking::with(['user'])
            ->where('booking_date', $date)
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->orderBy('start_time')
            ->get()
            ->groupBy('court_id');

        $maintenanceCourtIds = DB::table('court_maintenances')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_datetime', '<=', "$date 23:59:59")
            ->where('end_datetime', '>=', "$date 00:00:00")
            ->pluck('court_id')
            ->unique()
            ->flip();

        $courtsData = $courts->map(function ($court) use ($now, $bookingsByCourt, $maintenanceCourtIds) {
            $courtBookings = $bookingsByCourt->get($court->court_id) ?? collect();

            $currentBooking = $courtBookings->first(fn ($b) => in_array($b->status, ['checked_in', 'playing', 'extended'], true)
                && $b->start_time <= $now && $b->end_time > $now
            );

            $nextBooking = $courtBookings
                ->filter(fn ($b) => in_array($b->status, ['pending', 'confirmed'], true)
                    && $b->start_time > $now
                )
                ->sortBy('start_time')
                ->first();

            $todayBookings = $courtBookings->count();
            $hasMaintenance = $maintenanceCourtIds->has($court->court_id);

            $realtimeStatus = $court->status;
            if ($hasMaintenance) {
                $realtimeStatus = 'maintenance';
            } elseif ($currentBooking) {
                $realtimeStatus = 'playing';
            } elseif ($court->status === 'active') {
                $realtimeStatus = 'available';
            }

            return [
                'court_id' => $court->court_id,
                'court_name' => $court->court_name,
                'court_code' => $court->court_code,
                'type' => $court->type,
                'status' => $court->status,
                'realtime_status' => $realtimeStatus,
                'current_booking' => $currentBooking ? [
                    'booking_id' => $currentBooking->booking_id,
                    'booking_code' => $currentBooking->booking_code,
                    'user_name' => $currentBooking->user->full_name ?? 'Khách vãng lai',
                    'start_time' => $currentBooking->start_time,
                    'end_time' => $currentBooking->end_time,
                    'checked_in_at' => $currentBooking->checked_in_at,
                    'status' => $currentBooking->status,
                    'total_amount' => $currentBooking->total_amount,
                ] : null,
                'next_booking' => $nextBooking ? [
                    'booking_id' => $nextBooking->booking_id,
                    'user_name' => $nextBooking->user->full_name ?? 'Khách vãng lai',
                    'start_time' => $nextBooking->start_time,
                    'end_time' => $nextBooking->end_time,
                    'status' => $nextBooking->status,
                ] : null,
                'today_booking_count' => $todayBookings,
                'has_maintenance' => $hasMaintenance,
            ];
        });

        $todayStats = [
            'total_bookings' => CourtBooking::whereDate('booking_date', $date)->count(),
            'playing_now' => CourtBooking::whereDate('booking_date', $date)
                ->whereIn('status', ['checked_in', 'playing', 'extended'])
                ->where('start_time', '<=', $now)
                ->where('end_time', '>', $now)
                ->count(),
            'pending' => CourtBooking::whereDate('booking_date', $date)->where('status', 'pending')->count(),
            'completed' => CourtBooking::whereDate('booking_date', $date)->where('status', 'completed')->count(),
            'revenue_today' => CourtBooking::whereDate('booking_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount'),
        ];

        return [
            'courts' => $courtsData,
            'stats' => $todayStats,
            'server_time' => now()->format('H:i:s'),
            'date' => $date,
        ];
    }

    /**
     * Thống kê doanh thu & hiệu suất theo kỳ.
     */
    public function stats(Request $request): array
    {
        $period = $request->period ?? 'month';
        $fromDate = match ($period) {
            'day' => today(),
            'week' => today()->startOfWeek(),
            'month' => today()->startOfMonth(),
            default => today()->startOfMonth(),
        };
        $toDate = today();

        $revenueByCourt = CourtBooking::select('court_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as booking_count'))
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->groupBy('court_id')
            ->with('court:court_id,court_name,court_code')
            ->get();

        $revenueByDay = CourtBooking::select(
            DB::raw('DATE(booking_date) as date'),
            DB::raw('SUM(total_amount) as revenue'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->groupBy(DB::raw('DATE(booking_date)'))
            ->orderBy('date')
            ->get();

        $topServices = CourtBookingServiceModel::select(
            'service_id',
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(subtotal) as total_revenue')
        )
            ->whereHas('booking', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('booking_date', [$fromDate, $toDate]);
            })
            ->groupBy('service_id')
            ->orderByDesc('total_revenue')
            ->with('service:service_id,service_name,unit_price')
            ->limit(10)
            ->get();

        $courts = Court::where('status', 'active')->get();
        $daysInPeriod = $fromDate->diffInDays($toDate) + 1;
        $hoursPerDay = 17; // 05:00 - 22:00

        $bookedMinutesByCourt = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->whereIn('status', ['completed', 'checked_in', 'playing', 'extended'])
            ->select('court_id', DB::raw('SUM(duration_minutes) as total_minutes'))
            ->groupBy('court_id')
            ->pluck('total_minutes', 'court_id');

        $utilization = $courts->map(function ($court) use ($daysInPeriod, $hoursPerDay, $bookedMinutesByCourt) {
            $totalBookedMinutes = (int) ($bookedMinutesByCourt[$court->court_id] ?? 0);
            $totalAvailableMinutes = $daysInPeriod * $hoursPerDay * 60;
            $rate = $totalAvailableMinutes > 0 ? round(($totalBookedMinutes / $totalAvailableMinutes) * 100, 1) : 0;

            return [
                'court_id' => $court->court_id,
                'court_name' => $court->court_name,
                'booked_hours' => round($totalBookedMinutes / 60, 1),
                'total_hours' => $daysInPeriod * $hoursPerDay,
                'utilization_rate' => $rate,
            ];
        });

        $totalRevenue = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->sum('total_amount');
        $totalBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])->count();
        $completedBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->count();
        $cancelledBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'cancelled')->count();
        $noShowBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'no_show')->count();
        $serviceRevenue = CourtBookingServiceModel::whereHas('booking', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('booking_date', [$fromDate, $toDate]);
        })->sum('subtotal');

        return [
            'period' => $period,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'kpis' => [
                'total_revenue' => $totalRevenue,
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'no_show_bookings' => $noShowBookings,
                'service_revenue' => $serviceRevenue,
                'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0,
                'no_show_rate' => $totalBookings > 0 ? round(($noShowBookings / $totalBookings) * 100, 1) : 0,
                'avg_revenue_per_booking' => $completedBookings > 0 ? (int) round($totalRevenue / $completedBookings) : 0,
            ],
            'revenue_by_court' => $revenueByCourt,
            'revenue_by_day' => $revenueByDay,
            'top_services' => $topServices,
            'utilization' => $utilization,
        ];
    }

    /**
     * Tính giá booking theo court_prices (day_type + khung giờ). Fallback giá mặc định.
     */
    private function calcPrice($courtId, $bookingDate, string $startTime, string $endTime, int $durationMinutes): int
    {
        $dayOfWeek = Carbon::parse($bookingDate)->dayOfWeek;
        $dayType = in_array($dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';

        $courtPrice = CourtPrice::where('court_id', $courtId)
            ->where('is_active', true)
            ->where(function ($q) use ($dayType) {
                $q->where('day_type', $dayType)->orWhere('day_type', 'all');
            })
            ->where('from_time', '<=', $startTime)
            ->where('to_time', '>=', $endTime)
            ->first();

        $pricePerHour = $courtPrice ? $courtPrice->price_per_hour : self::DEFAULT_PRICE_PER_HOUR;

        return (int) round(($pricePerHour / 60) * $durationMinutes);
    }
}
