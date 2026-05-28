<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    private function getUserId()
    {
        $user = auth('api')->user();

        if ($user) {
            return $user->user_id;
        }

        if (auth('admin')->check()) {
            return auth('admin')->user()->getKey();
        }

        return null;
    }

    public function index(Request $request)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $orders = $this->orderService->getUserOrders(
            $userId,
            $request->get('status', 'all')
        );

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để đặt hàng!'
            ], 401);
        }

        $result = $this->orderService->createOrder(
            $userId,
            $request->validated(),
            $request
        );

        return response()->json(
            $result['body'],
            $result['status_code'] ?? 200
        );
    }

    public function show($id)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $order = $this->orderService->getUserOrderDetail($userId, $id);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc về bạn!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    public function cancel(CancelOrderRequest $request, $id)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

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

    public function getOrderIdByCode($orderCode)
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $orderId = $this->orderService->getOrderIdByCode($userId, $orderCode);

        if (!$orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng!'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $orderId
            ]
        ]);
    }
}