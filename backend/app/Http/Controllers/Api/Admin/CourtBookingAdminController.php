<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CourtBookingCancelledMail;
use App\Mail\CourtBookingConfirmedMail;
use App\Models\CourtBooking;
use App\Services\CourtBookingAdminService;
use App\Services\CourtBookingWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CourtBookingAdminController extends Controller
{
    public function __construct(
        private CourtBookingWorkflowService $workflowService,
        private CourtBookingAdminService $adminService
    ) {}

    private function adminId(): ?int
    {
        return auth()->guard('admin')->id();
    }

    /**
     * Lấy danh sách đặt sân dành cho Admin kèm theo bộ lọc (date, court_id, status, search).
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->adminService->paginate($request),
        ]);
    }

    /**
     * Tạo một lượt đặt sân mới cho khách vãng lai (POS / walk-in) bởi Admin.
     *
     * @return JsonResponse
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

        $result = $this->adminService->createWalkIn($validated, $this->adminId(), $request);

        if (! $result['ok']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đặt sân thành công.',
            'data' => $result['data'],
        ], $result['code']);
    }

    /**
     * Lấy thông tin chi tiết của một lượt đặt sân (bao gồm các quan hệ: user, court, staff, services, payments,...).
     *
     * @param  int  $id  ID lượt đặt sân
     * @return JsonResponse
     */
    public function show($id)
    {
        $booking = CourtBooking::with([
            'user', 'court', 'staff',
            'services.service', 'payments.processedBy',
            'statusHistories', 'extensions',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $booking,
        ]);
    }

    /**
     * Chuyển trạng thái đặt sân từ "Chờ duyệt" (pending) sang "Đã xác nhận" (confirmed).
     *
     * @param  int  $id  ID lượt đặt sân
     * @return JsonResponse
     */
    public function confirm(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ có thể xác nhận booking ở trạng thái "Chờ duyệt".',
            ], 400);
        }

        $booking = $this->workflowService->transition(
            $booking,
            'confirmed',
            'admin',
            $this->adminId(),
            $request->note ?? 'Admin xac nhan booking',
            ['confirmed_at' => now()],
            $request
        );

        // Gửi email xác nhận cho khách hàng
        $booking->loadMissing(['user', 'court']);
        if ($booking->user?->email) {
            try {
                Mail::to($booking->user->email)->queue(new CourtBookingConfirmedMail($booking));
            } catch (\Exception $e) {
                Log::warning('Failed to queue booking confirmed mail', [
                    'booking_id' => $booking->booking_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xác nhận booking thành công.',
            'data' => $booking,
        ]);
    }

    /**
     * Check-in (confirmed/pending/no_show → checked_in)
     */
    public function checkIn(Request $request, $id)
    {
        $booking = CourtBooking::findOrFail($id);

        if (! in_array($booking->status, ['confirmed', 'pending', 'no_show'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking phải ở trạng thái "Chờ duyệt", "Đã xác nhận" hoặc "Vắng mặt" để check-in.',
            ], 400);
        }

        $allowOverride = $request->boolean('allow_override', true);

        try {
            $this->workflowService->assertCheckInWindow($booking, $allowOverride);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }

        $booking = $this->workflowService->transition(
            $booking,
            'checked_in',
            'admin',
            $this->adminId(),
            'Lễ tân check-in nhận sân tại quầy',
            ['checked_in_at' => now()],
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in thành công.',
            'data' => $booking->load(['user', 'court', 'services.service', 'payments']),
        ]);
    }

    /**
     * Check-out (checked_in/playing/extended → completed)
     */
    public function checkOut(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_method' => 'nullable|in:cash,bank_transfer,pos_card,pos_transfer',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        $result = $this->adminService->checkOut((int) $id, $validated, $this->adminId(), $request);

        if (! $result['ok']) {
            $payload = ['status' => 'error', 'message' => $result['message']];
            if (isset($result['amount_due'])) {
                $payload['amount_due'] = $result['amount_due'];
            }

            return response()->json($payload, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Check-out thành công.',
            'data' => $result['data'],
        ]);
    }

    /**
     * Thêm dịch vụ vào booking
     */
    public function addService(Request $request, $id)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:court_services,service_id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $result = $this->adminService->addService((int) $id, $validated, $this->adminId(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm dịch vụ thành công.',
            'data' => $result['data'],
        ]);
    }

    /**
     * Gia hạn giờ chơi
     */
    public function extend(Request $request, $id)
    {
        $validated = $request->validate([
            'extension_minutes' => 'required|integer|min:15',
        ]);

        $result = $this->adminService->extend((int) $id, $validated, $this->adminId(), $request);

        if (! $result['ok']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Gia hạn thành công.',
            'data' => $result['data'],
        ]);
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
            'data' => $booking,
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
            'message' => 'Đã xóa booking.',
        ]);
    }

    /**
     * Hủy booking (pending/confirmed → cancelled)
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
                $this->adminId(),
                $validated['reason'] ?? 'Admin cancelled booking',
                [
                    'cancel_reason_type' => 'other',
                    'cancel_reason' => $validated['reason'] ?? 'Admin cancelled booking',
                    'cancelled_at' => now(),
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
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
            'data' => $booking,
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

        $payment = $this->workflowService->recordPayment($booking, $validated, 'admin', $this->adminId(), $request);

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
                $this->adminId(),
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

    /**
     * Quét QR Check-in toàn cục (nhận diện camera / scanner gun)
     */
    public function scanQr(Request $request)
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
            'allow_override' => 'nullable|boolean',
        ]);

        $allowOverride = $request->boolean('allow_override', true);
        $result = $this->adminService->scanAndCheckIn($validated['qr_data'], $this->adminId(), $request, $allowOverride);

        if (! $result['ok']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'] ?? 'Check-in thành công.',
            'data' => $result['data'],
        ], $result['code']);
    }

    public function calendar(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->adminService->calendar($request),
        ]);
    }

    public function dashboard(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->adminService->dashboard($request),
        ]);
    }

    /**
     * Thống kê doanh thu & hiệu suất
     */
    public function stats(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->adminService->stats($request),
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

        $conflicts = $this->adminService->checkConflicts($validated);

        return response()->json([
            'status' => 'success',
            'has_conflict' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Record split payment (e.g., partial deposit, partial cash)
     */
    public function splitPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:cash,vnpay,momo,bank_transfer',
            'payments.*.payment_type' => 'required|in:deposit,full,additional',
            'payments.*.amount' => 'required|integer|min:1000',
            'payments.*.transaction_code' => 'nullable|string|max:120',
            'payments.*.status' => 'nullable|in:pending,success,failed',
            'payments.*.note' => 'nullable|string|max:255',
        ]);

        $result = $this->adminService->splitPayment((int) $id, $validated, $this->adminId(), $request);

        if (! $result['ok']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Split payments recorded successfully.',
            'data' => $result['data'],
        ]);
    }
}
