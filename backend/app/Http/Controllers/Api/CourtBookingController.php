<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourtBooking\LockCourtBookingRequest;
use App\Http\Requests\CourtBooking\StoreCourtBookingRequest;
use App\Models\Admin;
use App\Models\CourtBooking;
use App\Notifications\SystemNotification;
use App\Services\CourtBookingService;
use App\Services\CourtBookingWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CourtBookingController extends Controller
{
    protected $bookingService;

    protected $workflowService;

    public function __construct(CourtBookingService $bookingService, CourtBookingWorkflowService $workflowService)
    {
        $this->bookingService = $bookingService;
        $this->workflowService = $workflowService;
    }

    /**
     * Tạm khóa slot (giữ chỗ) trong vòng 5 phút để user tiến hành thanh toán.
     * Tránh tình trạng tranh chấp lịch đặt (Double Booking).
     *
     * @param \App\Http\Requests\CourtBooking\LockCourtBookingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function lock(LockCourtBookingRequest $request)
    {
        try {
            $lock = $this->bookingService->lockSlot($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Slot locked successfully for 5 minutes.',
                'data' => $lock,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Chủ động hủy khóa slot (nhả slot) nếu user thoát ra không thanh toán nữa.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function releaseLock(Request $request)
    {
        $validated = $request->validate([
            'lock_token' => 'required|string|max:64',
        ]);

        $released = $this->bookingService->releaseLock($validated['lock_token'], auth()->guard('api')->id());

        return response()->json([
            'status' => 'success',
            'released' => $released,
        ]);
    }

    /**
     * Tạo một lượt đặt sân mới sau khi xác nhận thanh toán/giữ chỗ thành công.
     * Sẽ gửi thông báo hệ thống cho khách hàng và Admin.
     *
     * @param \App\Http\Requests\CourtBooking\StoreCourtBookingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCourtBookingRequest $request)
    {
        try {
            $booking = $this->bookingService->createBooking($request->validated());

            // Notify Customer
            $user = auth()->guard('api')->user();
            if ($user) {
                $user->notify(new SystemNotification(
                    'Đặt sân thành công',
                    'Bạn đã đặt sân thành công. Mã đặt sân: '.$booking->booking_code,
                    '/profile/court-bookings',
                    'calendar'
                ));
            }

            // Notify Staff and Admins
            $staffs = Admin::whereIn('role', ['admin', 'staff'])->get();
            if ($staffs->count() > 0) {
                Notification::send($staffs, new SystemNotification(
                    'Lịch đặt sân mới',
                    'Khách hàng '.($user->full_name ?? 'Khách').' vừa đặt sân. Mã: '.$booking->booking_code,
                    '/admin/court-bookings/'.$booking->booking_id,
                    'calendar'
                ));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Booking created successfully.',
                'data' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Lấy danh sách lịch sử đặt sân của người dùng hiện tại (đã đăng nhập).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $bookings = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->with(['court'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bookings,
        ]);
    }

    /**
     * Lấy thông tin chi tiết một lịch đặt sân của người dùng hiện tại.
     * Bao gồm cả dịch vụ, thanh toán và lịch sử thay đổi trạng thái.
     *
     * @param int $id ID của lượt đặt sân
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->with(['court', 'services', 'payments', 'statusHistories'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $booking,
        ]);
    }

    /**
     * Hủy lịch đặt sân bởi người dùng (nếu trạng thái hợp lệ để hủy).
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id ID của lượt đặt sân
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);

        $booking = $this->workflowService->cancelByUser($booking, $request->input('reason'), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
            'data' => $booking,
        ]);
    }

    public function pay(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())->findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,vnpay,momo,bank_transfer',
            'payment_type' => 'nullable|in:deposit,full,additional',
            'amount' => 'nullable|integer|min:1000',
            'transaction_code' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);

        $payment = $this->workflowService->recordPayment($booking, $validated, 'user', auth()->guard('api')->id(), $request);

        return response()->json([
            'status' => 'success',
            'message' => $payment->status === 'success' ? 'Payment recorded successfully.' : 'Payment initialized.',
            'data' => $payment,
        ], 201);
    }

    public function qr(Request $request, $id)
    {
        $booking = CourtBooking::where('user_id', auth()->guard('api')->id())->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'booking_id' => $booking->booking_id,
                'booking_code' => $booking->booking_code,
                'qr_token' => $this->workflowService->qrToken($booking),
                'qr_data' => $this->workflowService->qrPayload($booking),
            ],
        ]);
    }
}
