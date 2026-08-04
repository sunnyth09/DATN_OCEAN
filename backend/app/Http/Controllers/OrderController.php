<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * OrderController — Xử lý đơn hàng phía khách hàng.
 *
 * Authorization:
 *   - Tất cả routes qua middleware 'auth:api' (đặt ở routes/api.php).
 *   - StoreOrderRequest::authorize() chặn admin guard.
 *   - CancelOrderRequest::authorize() kiểm tra ownership.
 *   - show() dùng OrderPolicy::view() via $this->authorize().
 */
class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * GET /api/orders — Danh sách đơn hàng của user đang login.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $orders = $this->orderService->getUserOrders(
            $user->user_id,
            $request->get('status', 'all')
        );

        return response()->json([
            'status' => 'success',
            'data'   => $orders,
        ]);
    }

    /**
     * POST /api/orders — Tạo đơn hàng mới.
     * StoreOrderRequest::authorize() đã chặn admin guard.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user   = auth('api')->user();
        
        $lock = \Illuminate\Support\Facades\Cache::lock('order_checkout_' . $user->user_id, 5);

        if (!$lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống đang xử lý đơn hàng của bạn. Vui lòng không gửi yêu cầu liên tục!',
            ], 429);
        }

        try {
            $result = $this->orderService->createOrder(
                $user->user_id,
                $request->validated(),
                $request
            );

            return response()->json(
                $result['body'],
                $result['status_code'] ?? 200
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * POST /api/orders/guest — Tạo đơn hàng mới cho khách vãng lai.
     */
    public function storeGuest(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone'          => 'required|string|max:20',
            'email'          => 'required|email|max:255',
            'province'       => 'required|string|max:100',
            'district'       => 'required|string|max:100',
            'ward'           => 'required|string|max:100',
            'address_line'   => 'required|string|max:255',
            'province_code'  => 'nullable',
            'district_code'  => 'nullable',
            'ward_code'      => 'nullable',
            'payment_method' => 'required|string|in:cod,vnpay,momo,bank_transfer',
            'coupon_applied' => 'nullable|string',
            'note'           => 'nullable|string|max:500',
            'referral_code'  => 'nullable|string|max:20',
            'items'          => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity'   => 'required|integer|min:1',
        ], [
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in'       => 'Phương thức thanh toán không hợp lệ.',
            'recipient_name.required' => 'Vui lòng nhập họ tên người nhận.',
            'phone.required'          => 'Vui lòng nhập số điện thoại.',
            'email.required'          => 'Vui lòng nhập email để nhận xác nhận đơn hàng.',
            'email.email'             => 'Email không hợp lệ.',
            'province.required'       => 'Vui lòng chọn Tỉnh/Thành phố.',
            'district.required'       => 'Vui lòng chọn Quận/Huyện.',
            'ward.required'           => 'Vui lòng chọn Phường/Xã.',
            'address_line.required'   => 'Vui lòng nhập địa chỉ chi tiết.',
            'items.required'          => 'Giỏ hàng trống.',
        ]);

        $lock = \Illuminate\Support\Facades\Cache::lock('order_checkout_guest_' . $request->ip(), 5);

        if (!$lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống đang xử lý đơn hàng của bạn. Vui lòng không gửi yêu cầu liên tục!',
            ], 429);
        }

        try {
            $result = $this->orderService->createGuestOrder(
                $request->all(),
                $request
            );

            return response()->json(
                $result['body'],
                $result['status_code'] ?? 200
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * GET /api/orders/{id} — Chi tiết đơn hàng.
     * OrderPolicy::view() đảm bảo user chỉ xem đơn của mình.
     */
    public function show(string|int $id): JsonResponse
    {
        $order = is_numeric($id) ? Order::find($id) : Order::where('order_code', $id)->first();

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy đơn hàng!',
            ], 404);
        }

        Gate::authorize('view', $order); // 403 nếu không phải owner

        // Load chi tiết đầy đủ
        $detail = $this->orderService->getUserOrderDetail(
            auth('api')->user()->user_id,
            $order->order_id
        );

        return response()->json([
            'status' => 'success',
            'data'   => $detail,
        ]);
    }

    /**
     * POST /api/orders/{id}/cancel — Hủy đơn hàng.
     * CancelOrderRequest::authorize() đã kiểm tra ownership.
     */
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $result = $this->orderService->cancelOrder(
            auth('api')->user()->user_id,
            $id,
            $request->cancel_reason
        );

        return response()->json(
            $result['body'],
            $result['status_code'] ?? 200
        );
    }

    /**
     * GET /api/orders/by-code/{orderCode} — Tra cứu order_id từ order_code.
     */
    public function getOrderIdByCode(string $orderCode): JsonResponse
    {
        $orderId = $this->orderService->getOrderIdByCode(
            auth('api')->user()->user_id,
            $orderCode
        );

        if (!$orderId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Không tìm thấy đơn hàng!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => ['order_id' => $orderId],
        ]);
    }
}