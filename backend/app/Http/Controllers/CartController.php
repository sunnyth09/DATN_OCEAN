<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    /**
     * GET /cart — Lấy giỏ hàng của user hiện tại
     */
    public function getCart()
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để xem giỏ hàng!'
            ], 401);
        }

        return response()->json($this->cartService->getCart($userId));
    }

    /**
     * POST /cart/items — Thêm sản phẩm vào giỏ hàng
     */
    public function addItem(Request $request)
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập để thêm vào giỏ hàng!'], 401);
        }

        $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,variant_id',
            'quantity'   => 'required|integer|min:1|max:999',
        ], [
            'variant_id.required' => 'Vui lòng chọn phiên bản sản phẩm.',
            'variant_id.exists'   => 'Phiên bản sản phẩm không tồn tại.',
            'quantity.required'   => 'Vui lòng nhập số lượng.',
            'quantity.integer'    => 'Số lượng phải là số nguyên.',
            'quantity.min'        => 'Số lượng tối thiểu là 1.',
            'quantity.max'        => 'Số lượng tối đa là 999.',
        ]);

        $result = $this->cartService->addItem($userId, $request->only(['variant_id', 'quantity']));
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * PUT /cart/items/{id} — Cập nhật số lượng hoặc trạng thái selected
     */
    public function updateItem(Request $request, $id)
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:999',
            'selected' => 'sometimes|boolean',
        ], [
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min'     => 'Số lượng tối thiểu là 1.',
            'quantity.max'     => 'Số lượng tối đa là 999.',
        ]);

        $result = $this->cartService->updateItem($userId, $id, $request->only(['quantity', 'selected']));
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * DELETE /cart/items/{id} — Xóa 1 item khỏi giỏ
     */
    public function removeItem($id)
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $result = $this->cartService->removeItem($userId, $id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * DELETE /cart — Xóa toàn bộ giỏ hàng
     */
    public function clearCart()
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        return response()->json($this->cartService->clearCart($userId));
    }

    /**
     * PUT /cart/items/{id}/variant — Đổi biến thể
     */
    public function changeVariant(Request $request, $id)
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,variant_id',
        ]);

        $result = $this->cartService->changeVariant($userId, $id, $request->variant_id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * GET /cart/count — Lấy số lượng item trong giỏ
     */
    public function getCount()
    {
        $userId = $this->cartService->getUserId();
        return response()->json(['count' => $this->cartService->getCartCount($userId)]);
    }

    /**
     * POST /cart/buy-again/{orderId}
     */
    public function buyAgain(Request $request, $orderId)
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.'
            ], 401);
        }

        $result = $this->cartService->buyAgain($userId, (int) $orderId);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * GET /cart/upsell-suggestions
     */
    public function upsellSuggestions()
    {
        $userId = $this->cartService->getUserId();

        if (!$userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        return response()->json($this->cartService->getUpsellSuggestions($userId));
    }
}
