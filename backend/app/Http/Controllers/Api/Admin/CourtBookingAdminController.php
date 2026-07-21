<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CourtBooking;
use App\Models\CourtBookingStatusHistory;
use App\Models\CourtBookingExtension;
use App\Models\CourtBookingService;
use App\Models\CourtBookingPayment;
use App\Models\CourtService;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Services\CourtBookingWorkflowService;
use App\Mail\CourtBookingConfirmedMail;
use App\Mail\CourtBookingCancelledMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CourtBookingAdminController extends Controller
{
    public function __construct(private CourtBookingWorkflowService $workflowService)
    {
    }

    /**
     * Danh sách booking + filter (date, court_id, status, search)
     */
    public function index(Request $request)
    {
        $query = CourtBooking::with(['user', 'court', 'services.service', 'payments', 'extensions'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc');

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('booking_date', $request->date);
        }

        // Filter by court
        if ($request->filled('court_id')) {
            $query->where('court_id', $request->court_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('booking_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('booking_date', '<=', $request->to_date);
        }

        // Search by booking_code or user name
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

        $bookings = $query->paginate($request->per_page ?? 20);

            return response()->json([
                'status' => 'success',
            'data' => $bookings
        ]);
    }

    /**
     * Tạo booking cho khách vãng lai (POS / walk-in)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'user_id' => 'nullable|exists:users,user_id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'payment_method' => 'nullable|in:cash,vnpay,momo,bank_transfer,pos_card,pos_transfer',
            'customer_name' => 'nullable|string|max:120',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $startTime = $validated['start_time'] . ':00';
            $endTime = $validated['end_time'] . ':00';

            // Check overlap
            $conflict = CourtBooking::where('court_id', $validated['court_id'])
                ->where('booking_date', $validated['booking_date'])
                ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sân đã được đặt trong khung giờ này.'
                ], 409);
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
                return response()->json([
                    'status' => 'error',
                    'message' => 'Khung giờ này đang được khách giữ chỗ tạm thời.'
                ], 409);
            }

            $maintenanceConflict = DB::table('court_maintenances')
                ->where('court_id', $validated['court_id'])
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('start_datetime', '<', "{$validated['booking_date']} $endTime")
                ->where('end_datetime', '>', "{$validated['booking_date']} $startTime")
                ->exists();

            if ($maintenanceConflict) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sân đang bảo trì trong khung giờ này.'
                ], 409);
            }

            // Calculate duration
            $durationMinutes = Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
            $dayOfWeek = Carbon::parse($validated['booking_date'])->dayOfWeek;
            $dayType = in_array($dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';
            $courtPrice = \App\Models\CourtPrice::where('court_id', $validated['court_id'])
                ->where('is_active', true)
                ->where(function ($q) use ($dayType) {
                    $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                })
                ->where('from_time', '<=', $startTime)
                ->where('to_time', '>=', $endTime)
                ->first();
            $pricePerHour = $courtPrice ? $courtPrice->price_per_hour : 100000;
            $originalPrice = (int) round(($pricePerHour / 60) * $durationMinutes);

            // Generate booking code
            $bookingCode = 'BK-' . now()->format('Ymd') . '-' . str_pad(
                CourtBooking::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $booking = CourtBooking::create([
                'booking_code' => $bookingCode,
                'court_id' => $validated['court_id'],
                'user_id' => $validated['user_id'] ?? null,
                'staff_id' => auth()->guard('admin')->id(),
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

            // Log status history
            CourtBookingStatusHistory::create([
                'booking_id' => $booking->booking_id,
                'old_status' => null,
                'new_status' => 'confirmed',
                'note' => 'Đặt sân tại quầy (POS)',
                'actor_type' => 'admin',
                'actor_id' => auth()->guard('admin')->id(),
            ]);

            $this->workflowService->logActivity('booking.pos.created', $booking, null, $booking->toArray(), 'admin', auth()->guard('admin')->id(), $request);
            $this->workflowService->broadcast('CourtBookingCreated', $booking, ['source' => 'pos']);

            return response()->json([
                'status' => 'success',
                'message' => 'Đặt sân thành công.',
                'data' => $booking->load(['user', 'court'])
            ], 201);
        });
    }

    /**
     * Chi tiết booking (full relations)
     */
    public function show($id)
    {
        $booking = CourtBooking::with([
            'user', 'court', 'staff',
            'services.service', 'payments.processedBy',
            'statusHistories', 'extensions'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $booking
        ]);
    }

    /**
     * Xác nhận booking (pending → confirmed)
     */
    public function confirm(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ có thể xác nhận booking ở trạng thái "Chờ duyệt".'
            ], 400);
        }

        $booking = $this->workflowService->transition(
            $booking,
            'confirmed',
            'admin',
            auth()->guard('admin')->id(),
            $request->note ?? 'Admin xac nhan booking',
            ['confirmed_at' => now()],
            $request
        );

        /*
        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'confirmed',
            'note'       => $request->note ?? 'Admin xác nhận booking',
            'actor_type' => 'admin',
            'actor_id'   => auth()->guard('admin')->id(),
        ]);
        */

        // Gửi email xác nhận cho khách hàng
        $booking->loadMissing(['user', 'court']);
        if ($booking->user?->email) {
            try {
                Mail::to($booking->user->email)->queue(new CourtBookingConfirmedMail($booking));
            } catch (\Exception $e) {
                Log::warning('Failed to queue booking confirmed mail', [
                    'booking_id' => $booking->booking_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Xác nhận booking thành công.',
            'data'    => $booking,
        ]);
    }

    /**
     * Check-in (confirmed/pending → checked_in)
     */
    public function checkIn(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking phải ở trạng thái "Chờ duyệt" hoặc "Đã xác nhận" để check-in.'
            ], 400);
        }

        try {
            $this->workflowService->assertCheckInWindow($booking);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $booking = $this->workflowService->transition(
            $booking,
            'checked_in',
            'admin',
            auth()->guard('admin')->id(),
            'Check-in nhan san',
            ['checked_in_at' => now()],
            $request
        );

        /*
        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'checked_in',
            'note' => 'Check-in nhận sân',
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);
        */

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Check-out (checked_in/playing/extended → completed)
     */
    public function checkOut(Request $request, $id)
    {
        $booking = CourtBooking::with(['services', 'extensions'])->findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'nullable|in:cash,bank_transfer,pos_card,pos_transfer',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        if (!in_array($booking->status, ['checked_in', 'playing', 'extended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking phải ở trạng thái "Đang chơi" để check-out.'
            ], 400);
        }

        $remaining = (int) $booking->total_amount - (int) $booking->paid_amount;
        if ($remaining > 0 && empty($validated['payment_method'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking con tien chua thanh toan. Vui long chon phuong thuc thu tien truoc khi check-out.',
                'amount_due' => $remaining,
            ], 422);
        }

        return DB::transaction(function () use ($booking, $request, $validated) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $oldStatus = $booking->status;
            $remaining = (int) $booking->total_amount - (int) $booking->paid_amount;

            // Nếu còn tiền chưa thanh toán → tự động ghi nhận thanh toán tiền mặt khi trả sân
            if ($remaining > 0) {
                CourtBookingPayment::create([
                    'booking_id'       => $booking->booking_id,
                    'payment_type'     => 'full',
                    'payment_method'   => $validated['payment_method'],
                    'transaction_code' => $validated['transaction_code'] ?? 'CHECKOUT-' . $booking->booking_code . '-' . now()->format('His'),
                    'amount'           => $remaining,
                    'status'           => 'success',
                    'paid_at'          => now(),
                    'note'             => 'Thanh toán khi check-out',
                    'processed_by'     => auth()->guard('admin')->id(),
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
                auth()->guard('admin')->id(),
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
                'note'       => 'Check-out trả sân & thanh toán',
                'actor_type' => 'admin',
                'actor_id'   => auth()->guard('admin')->id(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Check-out thành công.',
                'data'    => $booking->load(['payments']),
            ]);
        });
    }

    /**
     * Thêm dịch vụ vào booking
     */
    public function addService(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        $validated = $request->validate([
            'service_id' => 'required|exists:court_services,service_id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string'
        ]);

        return DB::transaction(function () use ($booking, $validated, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $service = CourtService::findOrFail($validated['service_id']);
            $subtotal = $service->unit_price * $validated['quantity'];

            $bookingService = CourtBookingService::create([
                'booking_id' => $booking->booking_id,
                'service_id' => $service->service_id,
                'quantity' => $validated['quantity'],
                'unit_price' => $service->unit_price,
                'subtotal' => $subtotal,
                'note' => $validated['note'] ?? null,
                'added_by' => auth()->guard('admin')->id(),
            ]);

            $booking->service_amount += $subtotal;
            $booking->total_amount += $subtotal;
            $booking->save();
            $this->workflowService->logActivity('booking.service.added', $booking, null, $bookingService->toArray(), 'admin', auth()->guard('admin')->id(), $request);
            $this->workflowService->broadcast('CourtBookingServiceAdded', $booking, ['service_amount' => $booking->service_amount, 'total_amount' => $booking->total_amount]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm dịch vụ thành công.',
                'data' => $bookingService->load('service')
            ]);
        });
    }

    /**
     * Gia hạn giờ chơi
     */
    public function extend(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        $validated = $request->validate([
            'extension_minutes' => 'required|integer|min:15',
        ]);

        return DB::transaction(function () use ($booking, $validated, $request) {
            $booking = CourtBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

        $currentEnd = Carbon::parse($booking->end_time);
        $newEnd     = $currentEnd->copy()->addMinutes($validated['extension_minutes']);

        // Chặn gia hạn vượt qua nửa đêm: end_time là cột TIME, nếu cộng phút khiến
        // sang ngày mới thì "H:i:s" sẽ nhỏ hơn end_time hiện tại → mọi so sánh overlap
        // theo TIME đều sai. Không cho phép và yêu cầu xử lý thủ công.
        if ($newEnd->format('Y-m-d') !== $currentEnd->format('Y-m-d')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gia hạn qua nửa đêm. Vui lòng tạo booking mới cho ngày hôm sau.'
            ], 400);
        }

        $newEndTime = $newEnd->format('H:i:s');

        // Chặn gia hạn vượt quá giờ đóng cửa của sân trong ngày (nếu có lịch mở cửa).
        $schedule = \App\Models\CourtSchedule::where('court_id', $booking->court_id)
            ->where('day_of_week', $booking->booking_date->dayOfWeek)
            ->where('is_active', true)
            ->first();
        if ($schedule && $schedule->close_time && $newEndTime > Carbon::parse($schedule->close_time)->format('H:i:s')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gia hạn vượt quá giờ đóng cửa của sân.'
            ], 400);
        }

        // Check conflict (đã nằm trong lockForUpdate của booking + query index-safe)
        $conflict = DB::table('court_bookings')
            ->where('court_id', $booking->court_id)
            ->where('booking_date', $booking->booking_date)
            ->where('booking_id', '!=', $booking->booking_id)
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->where('start_time', '<', $newEndTime)
            ->where('end_time', '>', $booking->end_time)
            ->exists();

        if ($conflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gia hạn. Sân đã được đặt cho khung giờ tiếp theo.'
            ], 400);
        }

        $lockConflict = DB::table('court_booking_locks')
            ->where('court_id', $booking->court_id)
            ->where('booking_date', $booking->booking_date)
            ->where('expires_at', '>', now())
            ->where('start_time', '<', $newEndTime)
            ->where('end_time', '>', $booking->end_time)
            ->exists();

        if ($lockConflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khong the gia han vi khung gio tiep theo dang duoc giu cho.'
            ], 400);
        }

        $maintenanceConflict = DB::table('court_maintenances')
            ->where('court_id', $booking->court_id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_datetime', '<', "{$booking->booking_date->format('Y-m-d')} $newEndTime")
            ->where('end_datetime', '>', "{$booking->booking_date->format('Y-m-d')} {$booking->end_time}")
            ->exists();

        if ($maintenanceConflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khong the gia han vi san co lich bao tri.'
            ], 400);
        }

        // Lấy giá thực tế từ court_prices theo ngày/giờ
        $bookingDate  = $booking->booking_date instanceof \Carbon\Carbon
            ? $booking->booking_date
            : \Carbon\Carbon::parse($booking->booking_date);
        $dayOfWeek = $bookingDate->dayOfWeek;
        $dayType   = in_array($dayOfWeek, [0, 6]) ? 'weekend' : 'weekday';
        $currentEndTime = $booking->end_time;

        $courtPrice = CourtPrice::where('court_id', $booking->court_id)
            ->where('is_active', true)
            ->where(function ($q) use ($dayType) {
                $q->where('day_type', $dayType)->orWhere('day_type', 'all');
            })
            ->where('from_time', '<=', $currentEndTime)
            ->where('to_time', '>=', $newEndTime)
            ->first();

        // Fallback: lấy bất kỳ giá nào của sân trong ngày này
        if (!$courtPrice) {
            $courtPrice = CourtPrice::where('court_id', $booking->court_id)
                ->where('is_active', true)
                ->where(function ($q) use ($dayType) {
                    $q->where('day_type', $dayType)->orWhere('day_type', 'all');
                })
                ->first();
        }

        $pricePerHour = $courtPrice ? (float) $courtPrice->price_per_hour : 100000;
        $extraAmount  = (int) round(($pricePerHour / 60) * $validated['extension_minutes']);

        $extension = CourtBookingExtension::create([
            'booking_id' => $booking->booking_id,
            'original_end_time' => $booking->end_time,
            'extended_end_time' => $newEndTime,
            'extension_minutes' => $validated['extension_minutes'],
            'extra_amount' => $extraAmount,
            'approved_by' => auth()->guard('admin')->id(),
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
        ], 'admin', auth()->guard('admin')->id(), $request);
        $this->workflowService->broadcast('CourtBookingStatusChanged', $booking, ['old_status' => $oldStatus, 'new_status' => 'extended']);

        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => $oldStatus,
            'new_status' => 'extended',
            'note' => "Gia hạn thêm {$validated['extension_minutes']} phút",
            'actor_type' => 'admin',
            'actor_id' => auth()->guard('admin')->id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Gia hạn thành công.',
            'data' => $extension
        ]);
        });
    }

    /**
     * Cập nhật booking
     */
    public function update(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $booking->update($request->only(['note', 'payment_method']));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thành công.',
            'data' => $booking
        ]);
    }

    /**
     * Xóa booking (soft delete)
     */
    public function destroy($id)
    {
        $booking = CourtBooking::findOrFail($id);
        $booking->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xóa booking.'
        ]);
    }

    /**
     * Dashboard Lễ Tân — Trạng thái realtime 7 sân
     */
    public function cancel(Request $request, $id)
    {
        $booking = CourtBooking::whereIn('status', ['pending', 'confirmed'])->findOrFail($id);
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $booking = $this->workflowService->transition(
                $booking,
                'cancelled',
                'admin',
                auth()->guard('admin')->id(),
                $validated['reason'] ?? 'Admin cancelled booking',
                [
                    'cancel_reason_type' => 'other',
                    'cancel_reason'      => $validated['reason'] ?? 'Admin cancelled booking',
                    'cancelled_at'       => now(),
                ],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        // Gửi email thông báo hủy cho khách hàng
        $booking->loadMissing(['user', 'court']);
        if ($booking->user?->email) {
            try {
                Mail::to($booking->user->email)->queue(new CourtBookingCancelledMail($booking, null, 'admin'));
            } catch (\Exception $e) {
                Log::warning('Failed to queue booking cancelled mail (admin)', [
                    'booking_id' => $booking->booking_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Booking cancelled successfully.',
            'data'    => $booking,
        ]);
    }

    public function recordPayment(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,vnpay,momo,bank_transfer,pos_card,pos_transfer',
            'payment_type' => 'nullable|in:deposit,full,additional',
            'amount' => 'nullable|integer|min:1000',
            'status' => 'nullable|in:pending,success,failed',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        $payment = $this->workflowService->recordPayment($booking, $validated, 'admin', auth()->guard('admin')->id(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    public function qrCheckIn(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);
        $validated = $request->validate([
            'qr_token' => 'required|string',
        ]);

        try {
            $this->workflowService->assertValidQrToken($booking, $validated['qr_token']);
            $this->workflowService->assertCheckInWindow($booking);
            $booking = $this->workflowService->transition(
                $booking,
                'checked_in',
                'admin',
                auth()->guard('admin')->id(),
                'QR check-in',
                ['checked_in_at' => now()],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'QR check-in successful.',
            'data' => $booking,
        ]);
    }

    public function calendar(Request $request)
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'mode' => $mode,
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'items' => $bookings,
                'by_date' => $bookings->groupBy(fn ($booking) => $booking->booking_date->format('Y-m-d')),
            ],
        ]);
    }

    public function dashboard(Request $request)
    {
        $date = $request->date ?? today()->toDateString();
        $now = now()->format('H:i:s');

        $courts = Court::with(['schedules'])->orderBy('sort_order')->get();

        // Batch-load toàn bộ booking blocking trong ngày + maintenance (mỗi loại 1 query),
        // rồi group theo court_id — tránh N+1 (trước đây mỗi sân bắn 4 query).
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

            // Current booking (đang chơi ngay bây giờ)
            $currentBooking = $courtBookings->first(fn ($b) =>
                in_array($b->status, ['checked_in', 'playing', 'extended'], true)
                && $b->start_time <= $now && $b->end_time > $now
            );

            // Next booking
            $nextBooking = $courtBookings
                ->filter(fn ($b) =>
                    in_array($b->status, ['pending', 'confirmed'], true)
                    && $b->start_time > $now
                )
                ->sortBy('start_time')
                ->first();

            // All bookings today
            $todayBookings = $courtBookings->count();

            // Check maintenance
            $hasMaintenance = $maintenanceCourtIds->has($court->court_id);

            // Determine real-time status
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

        // Summary stats
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'courts' => $courtsData,
                'stats' => $todayStats,
                'server_time' => now()->format('H:i:s'),
                'date' => $date,
            ]
        ]);
    }

    /**
     * Thống kê doanh thu & hiệu suất
     */
    public function stats(Request $request)
    {
        $period = $request->period ?? 'month'; // day, week, month
        $fromDate = match ($period) {
            'day' => today(),
            'week' => today()->startOfWeek(),
            'month' => today()->startOfMonth(),
            default => today()->startOfMonth(),
        };
        $toDate = today();

        // Revenue by court
        $revenueByCourt = CourtBooking::select('court_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as booking_count'))
            ->whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->groupBy('court_id')
            ->with('court:court_id,court_name,court_code')
            ->get();

        // Revenue by day (for chart)
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

        // Top services
        $topServices = CourtBookingService::select(
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

        // Court utilization (% hours booked vs total hours open)
        $courts = Court::where('status', 'active')->get();
        $daysInPeriod = $fromDate->diffInDays($toDate) + 1;
        $hoursPerDay = 17; // 05:00 - 22:00

        // Gom tổng số phút đã đặt theo từng sân trong 1 query (tránh N+1: trước đây
        // mỗi sân bắn 1 query SUM riêng).
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

        // Overall KPIs
        $totalRevenue = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->sum('total_amount');
        $totalBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])->count();
        $completedBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'completed')->count();
        $cancelledBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'cancelled')->count();
        $noShowBookings = CourtBooking::whereBetween('booking_date', [$fromDate, $toDate])
            ->where('status', 'no_show')->count();
        $serviceRevenue = CourtBookingService::whereHas('booking', function ($q) use ($fromDate, $toDate) {
            $q->whereBetween('booking_date', [$fromDate, $toDate]);
        })->sum('subtotal');

        return response()->json([
            'status' => 'success',
            'data' => [
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
            ]
        ]);
    }

    /**
     * Check if a dragged booking conflicts with existing bookings.
     */
    public function checkConflicts(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,court_id',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'exclude_booking_id' => 'nullable|integer',
        ]);

        $query = CourtBooking::where('court_id', $validated['court_id'])
            ->where('booking_date', $validated['booking_date'])
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time']);

        if (!empty($validated['exclude_booking_id'])) {
            $query->where('booking_id', '!=', $validated['exclude_booking_id']);
        }

        $conflicts = $query->get();

        return response()->json([
            'status' => 'success',
            'has_conflict' => $conflicts->isNotEmpty(),
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Record split payment (e.g., partial deposit, partial cash)
     */
    public function splitPayment(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:cash,vnpay,momo,bank_transfer',
            'payments.*.payment_type' => 'required|in:deposit,full,additional',
            'payments.*.amount' => 'required|integer|min:1000',
            'payments.*.transaction_code' => 'nullable|string|max:120',
            'payments.*.note' => 'nullable|string|max:255',
        ]);

        $recordedPayments = [];
        try {
            DB::transaction(function () use ($booking, $validated, &$recordedPayments, $request) {
                foreach ($validated['payments'] as $paymentData) {
                    $recordedPayments[] = $this->workflowService->recordPayment(
                        $booking,
                        $paymentData,
                        'admin',
                        auth()->guard('admin')->id(),
                        $request
                    );
                    
                    if ($paymentData['payment_type'] === 'deposit') {
                        $booking->deposit_amount += $paymentData['amount'];
                    }
                }
                $booking->save();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Split payments recorded successfully.',
                'data' => $recordedPayments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
