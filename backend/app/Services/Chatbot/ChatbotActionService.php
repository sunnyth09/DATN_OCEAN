<?php

namespace App\Services\Chatbot;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\AddressRepository;
use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotActionService
{
    private const ORDER_TOKEN_TTL_MINUTES = 10;

    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService,
        protected AddressRepository $addressRepository,
        protected CartRepository $cartRepository,
        protected CouponService $couponService,
        protected ShippingService $shippingService
    ) {}

    public function execute(string $action, array $arguments, $customer = null, ?Request $request = null): array
    {
        return match ($action) {
            'search_products' => $this->searchProducts($arguments),
            'get_product_detail' => $this->getProductDetail($arguments),
            'add_to_cart' => $this->addToCart($arguments, $customer),
            'get_my_addresses' => $this->getMyAddresses($customer),
            'prepare_order' => $this->prepareOrder($arguments, $customer),
            'confirm_order' => $this->confirmOrder($arguments, $customer, $request),
            default => $this->error('Function không tồn tại hoặc không được phép.'),
        };
    }

    public function addToCart(array $arguments, $customer = null): array
    {
        if (!$customer) {
            return $this->requiresLogin('Bạn cần đăng nhập tài khoản khách hàng để thêm sản phẩm vào giỏ hàng.');
        }

        $variantId = (int) ($arguments['variant_id'] ?? 0);
        $productId = (int) ($arguments['product_id'] ?? 0);
        $quantity = max(1, min((int) ($arguments['quantity'] ?? 1), 20));

        if (!$variantId) {
            return $this->error('Vui lòng chọn màu/size cụ thể trước khi thêm vào giỏ hàng.', 'need_variant');
        }

        $variant = ProductVariant::with('product')
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->first();

        if (!$variant || !$variant->product || $variant->product->status !== 'active') {
            return $this->error('Biến thể sản phẩm không khả dụng.', 'invalid_variant');
        }

        if ($productId && (int) $variant->product_id !== $productId) {
            return $this->error('Biến thể không thuộc sản phẩm đã chọn.', 'invalid_variant');
        }

        $result = $this->cartService->addItem($customer->user_id, [
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);

        if (($result['status'] ?? 'error') !== 'success') {
            return [
                'status' => 'error',
                'message' => $result['message'] ?? 'Không thể thêm sản phẩm vào giỏ hàng.',
                'data' => $result,
            ];
        }

        $this->selectOnlyVariantForChatbotCheckout($customer->user_id, $variantId);

        return [
            'status' => 'success',
            'message' => $result['message'] ?? 'Đã thêm sản phẩm vào giỏ hàng.',
            'data' => $this->cartSummary($customer->user_id),
        ];
    }

    public function getCheckoutAddresses($customer = null): array
    {
        if (!$customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để chọn địa chỉ giao hàng.');
        }

        $cart = $this->cartRepository->getActiveCart($customer->user_id);
        if (!$cart) {
            return $this->error('Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm vào giỏ trước khi chọn địa chỉ giao hàng.', 'empty_cart');
        }

        $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);
        if ($cartItems->isEmpty()) {
            return $this->error('Vui lòng chọn ít nhất một sản phẩm trong giỏ hàng để thanh toán.', 'empty_selected_cart');
        }

        foreach ($cartItems as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            if (!$variant || !$product || $variant->status !== 'active' || $product->status !== 'active' || $variant->stock < $item->quantity) {
                return $this->error('Một sản phẩm trong giỏ hàng hiện không còn khả dụng. Vui lòng kiểm tra lại giỏ hàng trước khi đặt.', 'invalid_cart_item');
            }
        }

        return $this->getMyAddresses($customer);
    }

    public function getMyAddresses($customer = null): array
    {
        if (!$customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để chọn địa chỉ giao hàng.');
        }

        $addresses = \App\Models\Address::query()
            ->where('user_id', $customer->user_id)
            ->select([
                'address_id', 'recipient_name', 'phone', 'address_line', 'ward', 'district',
                'province', 'ward_code', 'district_code', 'province_code', 'is_default', 'address_type',
            ])
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($address) => $this->formatAddress($address))
            ->values()
            ->toArray();

        if (empty($addresses)) {
            return $this->error('Bạn chưa có địa chỉ giao hàng. Vui lòng thêm địa chỉ trong tài khoản trước khi đặt hàng.', 'no_addresses');
        }

        return [
            'status' => 'success',
            'message' => 'Danh sách địa chỉ giao hàng của bạn.',
            'data' => $addresses,
        ];
    }

    public function prepareOrder(array $arguments, $customer = null): array
    {
        if (!$customer) {
            return $this->requiresLogin('Bạn cần đăng nhập tài khoản khách hàng để đặt hàng.');
        }

        $addressId = (int) ($arguments['address_id'] ?? 0);
        if (!$addressId) {
            return $this->error('Vui lòng chọn địa chỉ giao hàng trước khi đặt hàng.', 'need_address');
        }

        $paymentMethod = $arguments['payment_method'] ?? 'cod';
        if (!in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
            $paymentMethod = 'cod';
        }

        $address = $this->addressRepository->findUserAddress($customer->user_id, $addressId);
        if (!$address) {
            return $this->error('Địa chỉ giao hàng không hợp lệ hoặc không thuộc tài khoản của bạn.', 'invalid_address');
        }

        $cart = $this->cartRepository->getActiveCart($customer->user_id);
        if (!$cart) {
            return $this->error('Giỏ hàng của bạn đang trống.', 'empty_cart');
        }

        $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);
        if ($cartItems->isEmpty()) {
            return $this->error('Vui lòng chọn ít nhất một sản phẩm trong giỏ hàng để thanh toán.', 'empty_selected_cart');
        }

        try {
            $subtotal = 0;
            $items = [];
            foreach ($cartItems as $item) {
                $variant = $item->variant;
                $product = $variant?->product;
                if (!$variant || !$product || $variant->status !== 'active' || $product->status !== 'active') {
                    return $this->error('Một sản phẩm trong giỏ hàng hiện không còn khả dụng.', 'invalid_cart_item');
                }
                if ($variant->stock < $item->quantity) {
                    return $this->error('Sản phẩm ' . $product->name . ' không đủ tồn kho.', 'out_of_stock');
                }

                $unitPrice = (float) $variant->price;
                $lineTotal = $unitPrice * $item->quantity;
                $subtotal += $lineTotal;
                $items[] = [
                    'cart_item_id' => $item->cart_item_id,
                    'product_id' => $product->product_id,
                    'variant_id' => $variant->variant_id,
                    'name' => $product->name,
                    'variant_name' => $variant->variant_name,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'unit_price_formatted' => $this->formatMoney($unitPrice),
                    'line_total' => $lineTotal,
                    'line_total_formatted' => $this->formatMoney($lineTotal),
                ];
            }

            $couponCode = $arguments['coupon_applied'] ?? null;
            $couponResult = $this->couponService->applyCoupon($customer->user_id, $couponCode, $subtotal);
            if (!$couponResult['success']) {
                return $this->error($couponResult['message'], 'invalid_coupon');
            }

            $shippingFee = $this->shippingService->calculateShippingFee($address, $subtotal, $couponResult['coupon']);
            $discountAmount = (float) $couponResult['discount_amount'];
            $grandTotal = $subtotal + $shippingFee - $discountAmount;

            $token = Str::random(48);
            Cache::put($this->tokenKey($customer->user_id, $token), [
                'user_id' => $customer->user_id,
                'address_id' => $address->address_id,
                'payment_method' => $paymentMethod,
                'coupon_applied' => $couponCode,
                'note' => isset($arguments['note']) ? Str::limit((string) $arguments['note'], 500, '') : null,
                'cart_item_ids' => collect($items)->pluck('cart_item_id')->all(),
                'grand_total' => $grandTotal,
            ], now()->addMinutes(self::ORDER_TOKEN_TTL_MINUTES));

            return [
                'status' => 'success',
                'message' => 'Đây là bản xem trước đơn hàng. Vui lòng kiểm tra kỹ và bấm xác nhận nếu thông tin đúng.',
                'data' => [
                    'requires_confirmation' => true,
                    'confirmation_token' => $token,
                    'expires_in_minutes' => self::ORDER_TOKEN_TTL_MINUTES,
                    'items' => $items,
                    'address' => $this->formatAddress($address),
                    'totals' => [
                        'subtotal' => $subtotal,
                        'subtotal_formatted' => $this->formatMoney($subtotal),
                        'shipping_fee' => $shippingFee,
                        'shipping_fee_formatted' => $this->formatMoney($shippingFee),
                        'discount' => $discountAmount,
                        'discount_formatted' => $this->formatMoney($discountAmount),
                        'grand_total' => $grandTotal,
                        'grand_total_formatted' => $this->formatMoney($grandTotal),
                    ],
                    'payment_method' => $paymentMethod,
                    'payment_method_label' => $this->paymentMethodLabel($paymentMethod),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Chatbot order preview failed', ['user_id' => $customer->user_id, 'error' => $e->getMessage()]);
            return $this->error('Không thể chuẩn bị đơn hàng lúc này. Vui lòng thử lại sau.', 'preview_failed');
        }
    }

    public function confirmOrder(array $arguments, $customer = null, ?Request $request = null): array
    {
        if (!$customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để xác nhận đặt hàng.');
        }

        $token = (string) ($arguments['confirmation_token'] ?? '');
        if ($token === '') {
            return $this->error('Thiếu mã xác nhận đơn hàng.', 'missing_token');
        }

        $key = $this->tokenKey($customer->user_id, $token);
        $preview = Cache::pull($key);
        if (!$preview) {
            return $this->error('Bản xem trước đơn hàng đã hết hạn hoặc đã được sử dụng. Vui lòng kiểm tra lại đơn hàng.', 'expired_token');
        }

        $payload = [
            'address_id' => $preview['address_id'],
            'payment_method' => $preview['payment_method'] ?? 'cod',
            'coupon_applied' => $preview['coupon_applied'] ?? null,
            'note' => $preview['note'] ?? null,
        ];

        $result = $this->orderService->createOrder($customer->user_id, $payload, $request ?? request());
        $body = $result['body'] ?? [];

        if (($body['status'] ?? 'error') !== 'success') {
            return [
                'status' => 'error',
                'message' => $body['message'] ?? 'Không thể tạo đơn hàng. Vui lòng thử lại.',
                'data' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => $body['message'] ?? 'Đặt hàng thành công!',
            'data' => $body['data'] ?? null,
        ];
    }

    private function sanitizeSearchArgs(array $args): array
    {
        $safe = [];
        foreach (['keyword', 'category', 'color', 'size'] as $field) {
            if (!empty($args[$field]) && is_string($args[$field])) {
                $safe[$field] = mb_substr(strip_tags(trim($args[$field])), 0, 60);
            }
        }

        if (!empty($args['categories']) && is_array($args['categories'])) {
            $safe['categories'] = array_slice(array_values(array_filter(array_map(function ($category) {
                return is_string($category) ? mb_substr(strip_tags(trim($category)), 0, 60) : null;
            }, $args['categories']))), 0, 3);
        }

        foreach (['min_price', 'max_price'] as $field) {
            if (isset($args[$field]) && is_numeric($args[$field])) {
                $safe[$field] = (float) min(max((float) $args[$field], 0), 100000000);
            }
        }

        if (isset($safe['min_price'], $safe['max_price']) && $safe['min_price'] > $safe['max_price']) {
            [$safe['min_price'], $safe['max_price']] = [$safe['max_price'], $safe['min_price']];
        }

        return $safe;
    }

    public function searchProducts(array $args): array
    {
        $args = $this->sanitizeSearchArgs($args);
        $color = $args['color'] ?? null;
        $size = $args['size'] ?? null;

        $query = Product::query()
            ->select(['product_id', 'category_id', 'name', 'slug', 'short_description', 'thumbnail_url', 'status', 'min_price', 'max_price', 'rating_avg', 'sold_count'])
            ->where('status', 'active')
            ->with(['category:category_id,name', 'mainImage', 'variants' => function ($q) use ($color, $size) {
                $q->where('status', 'active')->where('stock', '>', 0);
                if ($color) $q->where('color', 'LIKE', "%{$color}%");
                if ($size) $q->where('size', 'LIKE', "%{$size}%");
                $q->orderBy('price', 'asc');
            }]);

        if (!empty($args['keyword'])) {
            $keyword = trim((string) $args['keyword']);
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('short_description', 'LIKE', "%{$keyword}%");
            });
        }

        if (!empty($args['categories'])) {
            $categories = array_slice((array) $args['categories'], 0, 3);
            $categoryIds = Category::where(function ($q) use ($categories) {
                foreach ($categories as $categoryName) {
                    $q->orWhere('name', 'LIKE', '%' . trim((string) $categoryName) . '%');
                }
            })->pluck('category_id');
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            }
        } elseif (!empty($args['category'])) {
            $categoryName = trim((string) $args['category']);
            $categoryIds = Category::where('name', 'LIKE', "%{$categoryName}%")->pluck('category_id');
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        if ($color || $size || !empty($args['min_price']) || !empty($args['max_price'])) {
            $query->whereHas('variants', function ($q) use ($color, $size, $args) {
                $q->where('status', 'active')->where('stock', '>', 0);
                if ($color) $q->where('color', 'LIKE', "%{$color}%");
                if ($size) $q->where('size', 'LIKE', "%{$size}%");
                if (!empty($args['min_price'])) $q->where('price', '>=', (float) $args['min_price']);
                if (!empty($args['max_price'])) $q->where('price', '<=', (float) $args['max_price']);
            });
        }

        $products = $query->orderByDesc('sold_count')->limit(6)->get();
        if ($products->isEmpty()) {
            return ['status' => 'no_results', 'message' => 'Không tìm thấy sản phẩm nào phù hợp.', 'data' => []];
        }

        $data = $products->map(fn ($product) => $this->formatProductCard($product))->toArray();

        return [
            'status' => 'success',
            'count' => count($data),
            'message' => 'Tìm thấy ' . count($data) . ' sản phẩm.',
            'data' => $data,
        ];
    }

    public function getProductDetail(array $args): array
    {
        $identifier = $args['product_id'] ?? $args['slug'] ?? $args['product_name'] ?? '';
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return $this->error('Vui lòng cung cấp sản phẩm cần xem chi tiết.', 'missing_product');
        }

        $product = Product::query()
            ->where('status', 'active')
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('product_id', (int) $identifier);
                }
                $q->orWhere('slug', $identifier)
                  ->orWhere('name', 'LIKE', "%{$identifier}%");
            })
            ->with(['category:category_id,name', 'variants' => function ($q) {
                $q->where('status', 'active')->orderBy('price', 'asc');
            }, 'images'])
            ->first();

        if (!$product) {
            return ['status' => 'not_found', 'message' => "Không tìm thấy sản phẩm \"{$identifier}\".", 'data' => null];
        }

        $thumbnail = $product->images->firstWhere('is_main', 1)?->image_url ?? $product->thumbnail_url;
        $variants = $product->variants->map(fn ($variant) => $this->formatVariant($variant))->toArray();

        return [
            'status' => 'success',
            'message' => 'Đã tìm thấy chi tiết sản phẩm.',
            'data' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'category' => $product->category?->name,
                'price_range' => $this->formatMoney($product->min_price) . ' - ' . $this->formatMoney($product->max_price),
                'thumbnail' => $thumbnail,
                'variants' => $variants,
                'rating' => $product->rating_avg,
                'sold_count' => $product->sold_count,
            ],
        ];
    }

    private function selectOnlyVariantForChatbotCheckout(int $userId, int $variantId): void
    {
        $cart = $this->cartRepository->getActiveCart($userId);
        if (!$cart) {
            return;
        }

        CartItem::where('cart_id', $cart->cart_id)->update(['selected' => false]);
        CartItem::where('cart_id', $cart->cart_id)
            ->where('variant_id', $variantId)
            ->update(['selected' => true]);
    }

    private function cartSummary(int $userId): array
    {
        $cart = $this->cartService->getCart($userId)['data'] ?? [];
        return [
            'cart_id' => $cart['cart_id'] ?? null,
            'items' => $cart['items'] ?? [],
            'total_items' => $cart['total_items'] ?? 0,
            'total_selected_items' => $cart['total_selected_items'] ?? 0,
            'total_price' => $cart['total_price'] ?? 0,
            'total_price_formatted' => $this->formatMoney((float) ($cart['total_price'] ?? 0)),
        ];
    }

    private function formatProductCard(Product $product): array
    {
        $variant = $product->variants->first();
        $thumbnail = $product->mainImage?->image_url ?? $product->thumbnail_url;
        $variants = $product->variants->map(fn ($v) => $this->formatVariant($v))->values()->toArray();

        return [
            'product_id' => $product->product_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $this->formatMoney($variant?->price ?? $product->min_price),
            'price_raw' => (float) ($variant?->price ?? $product->min_price ?? 0),
            'thumbnail' => $thumbnail,
            'category' => $product->category?->name,
            'sold_count' => $product->sold_count,
            'available_colors' => $product->variants->pluck('color')->unique()->filter()->values()->toArray(),
            'available_sizes' => $product->variants->pluck('size')->unique()->filter()->values()->toArray(),
            'matched_variant' => $variant?->variant_name,
            'variant_id' => $variant?->variant_id,
            'stock' => $variant?->stock,
            'variants' => $variants,
        ];
    }

    private function formatVariant(ProductVariant $variant): array
    {
        return [
            'variant_id' => $variant->variant_id,
            'variant_name' => $variant->variant_name,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => $this->formatMoney($variant->price),
            'price_raw' => (float) $variant->price,
            'stock' => $variant->stock,
            'status' => $variant->stock > 0 ? 'Còn hàng' : 'Hết hàng',
        ];
    }

    private function formatAddress($address): array
    {
        return [
            'address_id' => $address->address_id,
            'recipient_name' => $address->recipient_name,
            'phone_masked' => $this->maskPhone($address->phone),
            'summary' => implode(', ', array_filter([$address->address_line, $address->ward, $address->district, $address->province])),
            'is_default' => (bool) $address->is_default,
            'address_type' => $address->address_type,
            'ward_code' => $address->ward_code,
            'district_code' => $address->district_code,
            'province_code' => $address->province_code,
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $length = strlen($phone);
        if ($length <= 4) return str_repeat('*', $length);
        return str_repeat('*', max(0, $length - 4)) . substr($phone, -4);
    }

    private function formatMoney(float|int|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, ',', '.') . 'đ';
    }

    private function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'bank_transfer' => 'Chuyển khoản ngân hàng',
            default => 'Thanh toán khi nhận hàng (COD)',
        };
    }

    private function tokenKey(int $userId, string $token): string
    {
        return "chatbot_order_preview:{$userId}:{$token}";
    }

    private function requiresLogin(string $message): array
    {
        return ['status' => 'requires_login', 'message' => $message, 'data' => null];
    }

    private function error(string $message, string $status = 'error'): array
    {
        return ['status' => $status, 'message' => $message, 'data' => null];
    }
}
