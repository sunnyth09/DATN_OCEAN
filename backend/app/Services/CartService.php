<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\CartRepository;

class CartService
{
    public function __construct(
        protected CartRepository $cartRepository
    ) {}

    // ─── Auth helpers ──────────────────────────────────────────────────

    /**
     * Lấy user_id đúng (hỗ trợ cả guard api và admin)
     */
    public function getUserId(): ?int
    {
        $user = auth('api')->user();
        if ($user) return $user->user_id;

        if (auth('admin')->check()) {
            return auth('admin')->user()->getKey();
        }

        return null;
    }

    // ─── GET CART ──────────────────────────────────────────────────────

    /**
     * Lấy giỏ hàng đầy đủ của user
     */
    public function getCart(int $userId): array
    {
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return [
                'status' => 'success',
                'data' => [
                    'cart_id'     => null,
                    'items'       => [],
                    'total_items' => 0,
                    'total_price' => 0,
                ],
            ];
        }

        $cart->load(['items.variant.product.images' => function ($query) {
            $query->where('is_main', 1);
        }]);

        $items = $cart->items->map(function ($item) {
            $variant = $item->variant;
            $product = $variant ? $variant->product : null;
            $mainImage = $product ? $product->images->first() : null;

            return [
                'cart_item_id' => $item->cart_item_id,
                'variant_id'   => $item->variant_id,
                'quantity'     => $item->quantity,
                'selected'     => $item->selected,
                'variant'      => $variant ? [
                    'variant_id'       => $variant->variant_id,
                    'variant_name'     => $variant->variant_name,
                    'color'            => $variant->color,
                    'size'             => $variant->size,
                    'price'            => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'stock'            => $variant->stock,
                    'image_url'        => $variant->image_url,
                    'status'           => $variant->status,
                ] : null,
                'product' => $product ? [
                    'product_id'    => $product->product_id,
                    'name'          => $product->name,
                    'slug'          => $product->slug,
                    'thumbnail_url' => $product->thumbnail_url,
                    'main_image'    => $mainImage ? $mainImage->image_url : null,
                ] : null,
                'line_total' => $variant ? $variant->price * $item->quantity : 0,
            ];
        });

        $selectedItems = $items->where('selected', true);

        return [
            'status' => 'success',
            'data' => [
                'cart_id'              => $cart->cart_id,
                'items'                => $items->values(),
                'total_items'          => $items->sum('quantity'),
                'total_selected_items' => $selectedItems->sum('quantity'),
                'total_price'          => $selectedItems->sum('line_total'),
            ],
        ];
    }

    // ─── ADD ITEM ──────────────────────────────────────────────────────

    /**
     * Thêm sản phẩm vào giỏ hàng
     */
    public function addItem(int $userId, array $data): array
    {
        $variant = ProductVariant::find($data['variant_id']);

        if (!$variant || $variant->status !== 'active') {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Sản phẩm này hiện không khả dụng.'];
        }

        $cart = $this->cartRepository->getOrCreateActiveCart($userId);

        $existingItem = CartItem::where('cart_id', $cart->cart_id)
            ->where('variant_id', $data['variant_id'])
            ->first();

        $newQuantity = $existingItem
            ? $existingItem->quantity + $data['quantity']
            : $data['quantity'];

        if ($newQuantity > $variant->stock) {
            return [
                '_status'         => 422,
                'status'          => 'error',
                'message'         => "Số lượng vượt quá tồn kho. Chỉ còn {$variant->stock} sản phẩm.",
                'available_stock' => $variant->stock,
            ];
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $newQuantity]);
            $message = 'Đã cập nhật số lượng trong giỏ hàng!';
        } else {
            CartItem::create([
                'cart_id'    => $cart->cart_id,
                'variant_id' => $data['variant_id'],
                'quantity'   => $data['quantity'],
                'selected'   => true,
            ]);
            $message = 'Đã thêm sản phẩm vào giỏ hàng!';
        }

        $totalItems = CartItem::where('cart_id', $cart->cart_id)->sum('quantity');

        return [
            '_status'     => 200,
            'status'      => 'success',
            'message'     => $message,
            'total_items' => $totalItems,
        ];
    }

    // ─── UPDATE ITEM ───────────────────────────────────────────────────

    /**
     * Cập nhật số lượng hoặc trạng thái selected
     */
    public function updateItem(int $userId, int $itemId, array $data): array
    {
        $cartItem = $this->findOwnedCartItem($userId, $itemId);

        if (!$cartItem) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'];
        }

        if (isset($data['quantity'])) {
            $variant = ProductVariant::find($cartItem->variant_id);
            if ($variant && $data['quantity'] > $variant->stock) {
                return [
                    '_status'         => 422,
                    'status'          => 'error',
                    'message'         => "Số lượng vượt quá tồn kho. Chỉ còn {$variant->stock} sản phẩm.",
                    'available_stock' => $variant->stock,
                ];
            }
        }

        $updateData = [];
        if (isset($data['quantity'])) $updateData['quantity'] = $data['quantity'];
        if (isset($data['selected'])) $updateData['selected'] = $data['selected'];

        $cartItem->update($updateData);

        return ['_status' => 200, 'status' => 'success', 'message' => 'Đã cập nhật giỏ hàng!'];
    }

    // ─── REMOVE ITEM ───────────────────────────────────────────────────

    /**
     * Xóa 1 item khỏi giỏ
     */
    public function removeItem(int $userId, int $itemId): array
    {
        $cartItem = $this->findOwnedCartItem($userId, $itemId);

        if (!$cartItem) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'];
        }

        $cartItem->delete();

        return ['_status' => 200, 'status' => 'success', 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!'];
    }

    // ─── CLEAR CART ────────────────────────────────────────────────────

    /**
     * Xóa toàn bộ giỏ hàng
     */
    public function clearCart(int $userId): array
    {
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return ['status' => 'success', 'message' => 'Đã xóa toàn bộ giỏ hàng!'];
    }

    // ─── CHANGE VARIANT ────────────────────────────────────────────────

    /**
     * Đổi biến thể (màu/size) của một cart item
     */
    public function changeVariant(int $userId, int $itemId, int $newVariantId): array
    {
        $cartItem = $this->findOwnedCartItem($userId, $itemId);

        if (!$cartItem) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'];
        }

        $newVariant = ProductVariant::find($newVariantId);
        if (!$newVariant || $newVariant->status !== 'active') {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Biến thể sản phẩm không khả dụng.'];
        }

        // Kiểm tra variant mới thuộc cùng sản phẩm
        $oldVariant = ProductVariant::find($cartItem->variant_id);
        if (!$oldVariant || $oldVariant->product_id !== $newVariant->product_id) {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Biến thể không hợp lệ.'];
        }

        // Không thay đổi
        if ($cartItem->variant_id == $newVariantId) {
            return ['_status' => 200, 'status' => 'success', 'message' => 'Biến thể không thay đổi.'];
        }

        // Kiểm tra tồn kho
        if ($cartItem->quantity > $newVariant->stock) {
            return [
                '_status'         => 422,
                'status'          => 'error',
                'message'         => "Số lượng vượt quá tồn kho. Chỉ còn {$newVariant->stock} sản phẩm.",
                'available_stock' => $newVariant->stock,
            ];
        }

        // Kiểm tra variant mới đã có sẵn trong giỏ (merge)
        $cart = Cart::where('user_id', $userId)->where('status', 'active')->first();
        $existingItem = CartItem::where('cart_id', $cart->cart_id)
            ->where('variant_id', $newVariantId)
            ->where('cart_item_id', '!=', $itemId)
            ->first();

        if ($existingItem) {
            $mergedQty = $existingItem->quantity + $cartItem->quantity;
            if ($mergedQty > $newVariant->stock) {
                $mergedQty = $newVariant->stock;
            }
            $existingItem->update(['quantity' => $mergedQty]);
            $cartItem->delete();
        } else {
            $cartItem->update(['variant_id' => $newVariantId]);
        }

        return ['_status' => 200, 'status' => 'success', 'message' => 'Đã cập nhật biến thể sản phẩm!'];
    }

    // ─── CART COUNT ────────────────────────────────────────────────────

    /**
     * Lấy số lượng item trong giỏ
     */
    public function getCartCount(?int $userId): int
    {
        if (!$userId) return 0;

        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        return $cart ? $cart->items()->count() : 0;
    }

    // ─── BUY AGAIN ─────────────────────────────────────────────────────

    /**
     * Mua lại từ đơn hàng cũ
     */
    public function buyAgain(int $userId, int $orderId): array
    {
        $order = Order::where('user_id', $userId)->where('order_id', $orderId)->first();

        if (!$order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng.'];
        }

        $orderItems = OrderItem::where('order_id', $orderId)->get();
        $cart = $this->cartRepository->getOrCreateActiveCart($userId);
        $totalAdded = 0;
        $errorMessages = [];

        foreach ($orderItems as $orderItem) {
            $variant = ProductVariant::find($orderItem->variant_id);
            if (!$variant || $variant->status !== 'active') {
                $name = $orderItem->product_name;
                if ($orderItem->variant_name) {
                    $name .= ' (' . $orderItem->variant_name . ')';
                }
                $errorMessages[] = "Sản phẩm " . $name . " hiện không còn bán.";
                continue;
            }

            $existingItem = CartItem::where('cart_id', $cart->cart_id)
                ->where('variant_id', $variant->variant_id)
                ->first();

            $newQuantity = $existingItem
                ? $existingItem->quantity + $orderItem->quantity
                : $orderItem->quantity;

            if ($newQuantity > $variant->stock) {
                $name = $orderItem->product_name;
                if ($orderItem->variant_name) {
                    $name .= ' (' . $orderItem->variant_name . ')';
                }
                $errorMessages[] = "Số lượng vượt quá tồn kho cho sản phẩm " . $name . ".";
                continue;
            }

            if ($existingItem) {
                $existingItem->update(['quantity' => $newQuantity]);
            } else {
                CartItem::create([
                    'cart_id'    => $cart->cart_id,
                    'variant_id' => $variant->variant_id,
                    'quantity'   => $orderItem->quantity,
                    'selected'   => true,
                ]);
            }
            $totalAdded++;
        }

        $totalItems = CartItem::where('cart_id', $cart->cart_id)->sum('quantity');

        if ($totalAdded === 0 && count($errorMessages) > 0) {
            return [
                '_status' => 422,
                'status'  => 'error',
                'message' => implode(' ', $errorMessages),
            ];
        }

        return [
            '_status'     => 200,
            'status'      => 'success',
            'message'     => 'Đã thêm ' . $totalAdded . ' sản phẩm vào giỏ hàng!',
            'errors'      => $errorMessages,
            'total_items' => $totalItems,
        ];
    }

    // ─── UPSELL SUGGESTIONS ────────────────────────────────────────────

    /**
     * Trả về mốc Freeship và danh sách sản phẩm gợi ý
     */
    public function getUpsellSuggestions(int $userId): array
    {
        $freeshipThreshold = (int) config('shop.freeship_threshold', 500000);

        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$cart || $cart->items()->count() === 0) {
            return [
                'status' => 'success',
                'data'   => [
                    'freeship_threshold' => $freeshipThreshold,
                    'suggestions'        => [],
                ],
            ];
        }

        $cartItems = $cart->items()->with('variant.product')->get();

        $topProduct  = null;
        $topMaxPrice = 0;
        $cartProductIds = [];

        foreach ($cartItems as $item) {
            $product = $item->variant?->product;
            if (!$product) continue;

            $cartProductIds[] = $product->product_id;
            $price = (float) ($product->max_price ?? $product->min_price ?? 0);
            if ($price > $topMaxPrice) {
                $topMaxPrice = $price;
                $topProduct  = $product;
            }
        }

        $cartProductIds = array_unique($cartProductIds);

        if (!$topProduct || !$topProduct->category_id) {
            return [
                'status' => 'success',
                'data'   => [
                    'freeship_threshold' => $freeshipThreshold,
                    'suggestions'        => [],
                ],
            ];
        }

        $suggestions = Product::where('category_id', $topProduct->category_id)
            ->where('status', 'active')
            ->whereNotIn('product_id', $cartProductIds)
            ->where(function ($q) use ($topMaxPrice) {
                $q->where('min_price', '<', $topMaxPrice)
                  ->orWhere('max_price', '<', $topMaxPrice);
            })
            ->whereHas('variants', function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0);
            })
            ->with([
                'variants' => function ($q) {
                    $q->where('status', 'active')
                      ->where('stock', '>', 0)
                      ->orderBy('price', 'asc');
                },
                'mainImage',
            ])
            ->orderByDesc('sold_count')
            ->limit(4)
            ->get();

        $result = $suggestions->map(function ($product) {
            $variant = $product->variants->first();
            if (!$variant) return null;

            $originalPrice   = (float) $variant->price;
            $discountedPrice = round($originalPrice * 0.9);

            $thumbnail = $product->mainImage?->image_url
                ?? $product->thumbnail_url
                ?? null;

            return [
                'product_id'       => $product->product_id,
                'name'             => $product->name,
                'slug'             => $product->slug,
                'thumbnail_url'    => $thumbnail,
                'original_price'   => $originalPrice,
                'discounted_price' => $discountedPrice,
                'variant_id'       => $variant->variant_id,
                'stock'            => $variant->stock,
            ];
        })->filter()->values();

        return [
            'status' => 'success',
            'data'   => [
                'freeship_threshold' => $freeshipThreshold,
                'suggestions'        => $result,
            ],
        ];
    }

    // ─── PRIVATE HELPERS ───────────────────────────────────────────────

    /**
     * Tìm cart item và kiểm tra quyền sở hữu
     */
    private function findOwnedCartItem(int $userId, int $itemId): ?CartItem
    {
        return CartItem::where('cart_item_id', $itemId)
            ->whereHas('cart', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'active');
            })
            ->first();
    }
}
