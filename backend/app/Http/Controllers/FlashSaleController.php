<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuyFlashSaleRequest;
use App\Services\FlashSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
    public function __construct(
        protected FlashSaleService $flashSaleService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Danh sách Flash Sale Items đang active
    // GET /api/flash-sale
    // ─────────────────────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->flashSaleService->getPublicList(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — Lấy tồn kho hiện tại từ Redis (cho Progress Bar)
    // GET /api/flash-sale/{id}/stock?product_id=xxx
    // ─────────────────────────────────────────────────────────────────────────
    public function stock(Request $request, int $id): JsonResponse
    {
        $result = $this->flashSaleService->getStock($id, $request->query('product_id'));

        if ($result['state'] === 'sale_not_found') {
            return response()->json(['message' => 'Flash Sale không tồn tại.'], 404);
        }

        if ($result['state'] === 'item_not_found') {
            return response()->json(['message' => 'Sản phẩm không có trong Flash Sale.'], 404);
        }

        return response()->json(array_merge(['status' => 'success'], $result['data']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE — Mua Flash Sale (High-Concurrency safe)
    // POST /api/flash-sale/buy  [auth required, throttle:10,1]
    // ─────────────────────────────────────────────────────────────────────────
    public function buy(BuyFlashSaleRequest $request): JsonResponse
    {
        $user   = auth('api')->user() ?? auth('admin')->user();
        $userId = $user ? ($user->user_id ?? $user->getKey()) : null;

        if (!$userId || !$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }

        $result = $this->flashSaleService->buy(
            $user,
            (int) $userId,
            (int) $request->flash_sale_id,
            (int) $request->product_id,
            (int) ($request->quantity ?? 1),
            [
                'recipient_name'   => $request->recipient_name,
                'recipient_phone'  => $request->recipient_phone,
                'shipping_address' => $request->shipping_address,
                'payment_method'   => $request->payment_method,
            ]
        );

        if ($result['state'] === 'sold_out') {
            return response()->json([
                'message'  => $result['message'],
                'sold_out' => true,
            ], 400);
        }

        if ($result['state'] !== 'ok') {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'status'     => 'success',
            'message'    => '🎉 Đặt hàng thành công!',
            'order_code' => $result['order_code'],
            'remaining'  => $result['remaining'],
        ], 200);
    }
}
