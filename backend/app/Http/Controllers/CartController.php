<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
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

        if (! $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để xem giỏ hàng!',
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

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập để thêm vào giỏ hàng!'], 401);
        }

        $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,variant_id',
            'quantity' => 'required|integer|min:1|max:999',
        ], [
            'variant_id.required' => 'Vui lòng chọn phiên bản sản phẩm.',
            'variant_id.exists' => 'Phiên bản sản phẩm không tồn tại.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng tối thiểu là 1.',
            'quantity.max' => 'Số lượng tối đa là 999.',
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

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'quantity' => 'sometimes|integer|min:1|max:999',
            'selected' => 'sometimes|boolean',
        ], [
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng tối thiểu là 1.',
            'quantity.max' => 'Số lượng tối đa là 999.',
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

        if (! $userId) {
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

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        return response()->json($this->cartService->clearCart($userId));
    }

    /**
     * PUT /cart/select-all — Chọn / Bỏ chọn tất cả sản phẩm trong 1 request
     * Thay thế cho việc frontend gửi N request song song (Promise.all) gây vượt rate limit.
     */
    public function selectAll(Request $request)
    {
        $userId = $this->cartService->getUserId();

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'selected' => 'required|boolean',
        ]);

        $result = $this->cartService->selectAll($userId, (bool) $request->input('selected'));
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * PUT /cart/items/{id}/variant — Đổi biến thể
     */
    public function changeVariant(Request $request, $id)
    {
        $userId = $this->cartService->getUserId();

        if (! $userId) {
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
        if (auth('admin')->check()) {
            return response()->json(['count' => 0]);
        }

        $userId = $this->cartService->getUserId();

        return response()->json(['count' => $this->cartService->getCartCount($userId)]);
    }

    /**
     * POST /cart/buy-again/{orderId}
     */
    public function buyAgain(Request $request, $orderId)
    {
        $userId = $this->cartService->getUserId();

        if (! $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
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

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        return response()->json($this->cartService->getUpsellSuggestions($userId));
    }

    /**
     * POST /cart/sync — Đồng bộ giỏ hàng từ localStorage sau khi login
     */
    public function sync(Request $request)
    {
        $userId = $this->cartService->getUserId();

        if (! $userId) {
            return response()->json(['status' => 'error', 'message' => 'Bạn cần đăng nhập!'], 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $result = $this->cartService->syncCart($userId, $request->items);

        return response()->json($result);
    }

    /**
     * POST /cart/guest-details — Lấy chi tiết thông tin sản phẩm/biến thể cho khách vãng lai (guest)
     */
    public function getGuestDetails(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = collect($request->items);
        $variantIds = $items->pluck('variant_id')->all();

        $variants = ProductVariant::whereIn('variant_id', $variantIds)
            ->with(['product.images' => function ($query) {
                $query->where('is_main', 1);
            }])
            ->get()
            ->keyBy('variant_id');

        $resultItems = $items->map(function ($item) use ($variants) {
            $variantId = $item['variant_id'];
            $variant = $variants->get($variantId);
            $product = $variant ? $variant->product : null;
            $mainImage = $product ? $product->images->first() : null;

            return [
                'cart_item_id' => $variantId, // Dùng variant_id làm cart_item_id cho guest
                'variant_id' => $variantId,
                'quantity' => $item['quantity'],
                'selected' => isset($item['selected']) ? (bool) $item['selected'] : true,
                'variant' => $variant ? [
                    'variant_id' => $variant->variant_id,
                    'variant_name' => $variant->variant_name,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'price' => $variant->effective_price,
                    'original_price' => $variant->original_price,
                    'compare_at_price' => $variant->original_price ?: $variant->compare_at_price,
                    'stock' => $variant->stock,
                    'image_url' => $variant->image_url,
                    'status' => $variant->status,
                ] : null,
                'product' => $product ? [
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail_url' => $product->thumbnail_url,
                    'main_image' => $mainImage ? $mainImage->image_url : null,
                    'original_price' => $product->original_price,
                    'compare_at_price' => $product->compare_at_price,
                    'max_price' => $product->max_price,
                    'min_price' => $product->min_price,
                ] : null,
                'line_total' => $variant ? $variant->effective_price * $item['quantity'] : 0,
            ];
        })->filter(function ($item) {
            return $item['variant'] !== null;
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'cart_id' => null,
                'items' => $resultItems,
                'total_items' => $resultItems->sum('quantity'),
                'total_selected_items' => $resultItems->where('selected', true)->sum('quantity'),
                'total_price' => $resultItems->where('selected', true)->sum('line_total'),
                'freeship_threshold' => (int) config('shop.freeship_threshold', 500000),
            ],
        ]);

    }
}
