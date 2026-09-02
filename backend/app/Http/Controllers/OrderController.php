<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $user = $request->user() ?? auth('api')->user() ?? auth('admin')->user() ?? auth()->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đăng nhập để xem danh sách đơn hàng.',
            ], 401);
        }

        $userId = (int) ($user->user_id ?? $user->getKey());

        $orders = $this->orderService->getUserOrders(
            $userId,
            $request->get('status', 'all')
        );

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    /**
     * POST /api/orders — Tạo đơn hàng mới.
     * StoreOrderRequest::authorize() đã chặn admin guard.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user() ?? auth('api')->user() ?? auth('admin')->user() ?? auth()->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đăng nhập để đặt hàng.',
            ], 401);
        }

        $userId = (int) ($user->user_id ?? $user->getKey());

        $lock = Cache::lock('order_checkout_'.$userId, 5);

        if (! $lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống đang xử lý đơn hàng của bạn. Vui lòng không gửi yêu cầu liên tục!',
            ], 429);
        }

        try {
            $result = $this->orderService->createOrder(
                $userId,
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
        $validated = $request->validate([
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'province' => 'required|string|max:100',
            'district' => 'nullable|string|max:100', // Ocean Express không có quận
            'ward' => 'required|string|max:100',
            'address_line' => 'required|string|max:255',
            'province_code' => 'nullable',
            'district_code' => 'nullable',
            'ward_code' => 'nullable',
            'payment_method' => 'required|string|in:cod,vnpay,momo,bank_transfer',
            'coupon_applied' => 'prohibited',
            'note' => 'nullable|string|max:500',
            'referral_code' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
        ], [
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'coupon_applied.prohibited' => 'Vui lòng đăng nhập để sử dụng mã giảm giá.',
            'recipient_name.required' => 'Vui lòng nhập họ tên người nhận.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'email.required' => 'Vui lòng nhập email để nhận xác nhận đơn hàng.',
            'email.email' => 'Email không hợp lệ.',
            'province.required' => 'Vui lòng chọn Tỉnh/Thành phố.',
            'ward.required' => 'Vui lòng chọn Phường/Xã.',
            'address_line.required' => 'Vui lòng nhập địa chỉ chi tiết.',
            'items.required' => 'Giỏ hàng trống.',
        ]);

        $lock = Cache::lock('order_checkout_guest_'.$request->ip(), 5);

        if (! $lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Hệ thống đang xử lý đơn hàng của bạn. Vui lòng không gửi yêu cầu liên tục!',
            ], 429);
        }

        try {
            $result = $this->orderService->createGuestOrder(
                $validated,
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

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng!',
            ], 404);
        }

        $user = request()->user() ?? auth('api')->user() ?? auth('admin')->user() ?? auth()->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đăng nhập.',
            ], 401);
        }

        $userId = (int) ($user->user_id ?? $user->getKey());

        Gate::authorize('view', $order); // 403 nếu không phải owner

        // Load chi tiết đầy đủ
        $detail = $this->orderService->getUserOrderDetail(
            $userId,
            $order->order_id
        );

        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    /**
     * POST /api/orders/{id}/cancel — Hủy đơn hàng.
     * CancelOrderRequest::authorize() đã kiểm tra ownership.
     */
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $user = $request->user() ?? auth('api')->user() ?? auth('admin')->user() ?? auth()->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đăng nhập.',
            ], 401);
        }

        $userId = (int) ($user->user_id ?? $user->getKey());

        $result = $this->orderService->cancelOrder(
            $userId,
            $id,
            $request->cancel_reason
        );

        return response()->json(
            $result['body'],
            $result['status_code'] ?? 200
        );
    }

    /**
     * GET /api/orders/{orderCode}/order-id — Tra cứu order_id và payment_status từ order_code.
     */
    public function getOrderIdByCode(string $orderCode): JsonResponse
    {
        $user = request()->user() ?? auth('api')->user() ?? auth('admin')->user() ?? auth()->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng đăng nhập.',
            ], 401);
        }

        $userId = (int) ($user->user_id ?? $user->getKey());

        $order = $this->orderService->getOrderByCode(
            $userId,
            $orderCode
        );

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->order_id,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
            ],
        ]);
    }

    /**
     * GET /api/orders/status/{orderCode} — Tra cứu công khai trạng thái đơn hàng.
     * Hỗ trợ polling real-time cho cả Guest và User đã đăng nhập.
     */
    public function publicStatus(string $orderCode): JsonResponse
    {
        $order = Order::where('order_code', $orderCode)->first();

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng!',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->order_id,
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'fulfillment_status' => $order->fulfillment_status,
                'created_at' => $order->created_at?->toISOString(),
                'grand_total' => (float) $order->grand_total,
            ],
        ]);
    }
}
