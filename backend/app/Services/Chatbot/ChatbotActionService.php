<?php

namespace App\Services\Chatbot;

use App\Models\Address;
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
            'quick_order' => $this->quickOrder($arguments, $customer),
            'auto_order' => $this->autoOrder($arguments, $customer, $request),
            default => $this->error('Function không tồn tại hoặc không được phép.'),
        };
    }

    public function addToCart(array $arguments, $customer = null): array
    {
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập tài khoản khách hàng để thêm sản phẩm vào giỏ hàng.');
        }

        $variantId = (int) ($arguments['variant_id'] ?? 0);
        $productId = (int) ($arguments['product_id'] ?? 0);
        $quantity = max(1, min((int) ($arguments['quantity'] ?? 1), 20));

        if (! $variantId) {
            return $this->error('Vui lòng chọn màu/size cụ thể trước khi thêm vào giỏ hàng.', 'need_variant');
        }

        $variant = ProductVariant::with('product')
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->first();

        if (! $variant || ! $variant->product || $variant->product->status !== 'active') {
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
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để chọn địa chỉ giao hàng.');
        }

        $cart = $this->cartRepository->getActiveCart($customer->user_id);
        if (! $cart) {
            return $this->error('Giỏ hàng của bạn đang trống. Vui lòng thêm sản phẩm vào giỏ trước khi chọn địa chỉ giao hàng.', 'empty_cart');
        }

        $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);
        if ($cartItems->isEmpty()) {
            return $this->error('Vui lòng chọn ít nhất một sản phẩm trong giỏ hàng để thanh toán.', 'empty_selected_cart');
        }

        foreach ($cartItems as $item) {
            $variant = $item->variant;
            $product = $variant?->product;
            if (! $variant || ! $product || $variant->status !== 'active' || $product->status !== 'active' || $variant->stock < $item->quantity) {
                return $this->error('Một sản phẩm trong giỏ hàng hiện không còn khả dụng. Vui lòng kiểm tra lại giỏ hàng trước khi đặt.', 'invalid_cart_item');
            }
        }

        return $this->getMyAddresses($customer);
    }

    public function getMyAddresses($customer = null): array
    {
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để chọn địa chỉ giao hàng.');
        }

        $addresses = Address::query()
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
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập tài khoản khách hàng để đặt hàng.');
        }

        $addressId = (int) ($arguments['address_id'] ?? 0);
        if (! $addressId) {
            return $this->error('Vui lòng chọn địa chỉ giao hàng trước khi đặt hàng.', 'need_address');
        }

        $paymentMethod = $arguments['payment_method'] ?? 'cod';
        if (! in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
            $paymentMethod = 'cod';
        }

        $address = $this->addressRepository->findUserAddress($customer->user_id, $addressId);
        if (! $address) {
            return $this->error('Địa chỉ giao hàng không hợp lệ hoặc không thuộc tài khoản của bạn.', 'invalid_address');
        }

        $cart = $this->cartRepository->getActiveCart($customer->user_id);
        if (! $cart) {
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
                if (! $variant || ! $product || $variant->status !== 'active' || $product->status !== 'active') {
                    return $this->error('Một sản phẩm trong giỏ hàng hiện không còn khả dụng.', 'invalid_cart_item');
                }
                if ($variant->stock < $item->quantity) {
                    return $this->error('Sản phẩm '.$product->name.' không đủ tồn kho.', 'out_of_stock');
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
            if (! $couponResult['success']) {
                return $this->error($couponResult['message'], 'invalid_coupon');
            }

            $shippingFee = $this->shippingService->calculateShippingFee(
                $address,
                $subtotal,
                $couponResult['coupon'],
                $this->shippingService->calculateWeight($cartItems)
            );
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
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để xác nhận đặt hàng.');
        }

        $token = (string) ($arguments['confirmation_token'] ?? '');
        if ($token === '') {
            return $this->error('Thiếu mã xác nhận đơn hàng.', 'missing_token');
        }

        $key = $this->tokenKey($customer->user_id, $token);
        $preview = Cache::pull($key);
        if (! $preview) {
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

    // ================================================================
    //  AUTO ORDER — AI đặt hàng tự động hoàn toàn không cần click
    // ================================================================

    /**
     * Auto Order: AI tự tìm sản phẩm, chọn variant tốt nhất, đặt và confirm đơn luôn.
     * Khác quickOrder: không hỏi lại khi nhiều variant, tự confirm không cần token.
     */
    public function autoOrder(array $args, $customer = null, ?Request $request = null): array
    {
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để sử dụng tính năng đặt hàng tự động.');
        }

        $args = $this->sanitizeSearchArgs($args);
        $keyword = trim($args['keyword'] ?? '');
        if ($keyword === '') {
            return $this->error('Vui lòng cho biết bạn muốn mua sản phẩm gì.', 'missing_keyword');
        }

        $color = $args['color'] ?? null;
        $size = $args['size'] ?? null;
        $quantity = max(1, min((int) ($args['quantity'] ?? 1), 20));

        // ── Step 1: Tìm sản phẩm best-match ──────────────────────────
        $keywordLen = mb_strlen($keyword);
        $product = Product::query()
            ->select(['product_id', 'category_id', 'name', 'slug', 'short_description', 'thumbnail_url', 'status', 'min_price', 'max_price', 'sold_count'])
            ->where('status', 'active')
            ->where(function ($q) use ($keyword, $keywordLen) {
                $q->where('name', 'LIKE', "%{$keyword}%");
                if ($keywordLen > 3) {
                    $q->orWhere('short_description', 'LIKE', "%{$keyword}%");
                }
            })
            ->with(['category:category_id,name', 'mainImage', 'variants' => function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0)->orderBy('price', 'asc');
            }])
            ->orderByDesc('sold_count')
            ->first();

        if (! $product) {
            return [
                'status' => 'not_found',
                'message' => "Không tìm thấy sản phẩm nào tên chứa \"{$keyword}\" trong hệ thống. Bạn có muốn tìm sản phẩm tương tự không?",
                'data' => null,
            ];
        }

        $variants = $product->variants;
        if ($variants->isEmpty()) {
            return $this->error("{$product->name} hiện đã hết hàng toàn bộ.", 'out_of_stock');
        }

        // ── Step 2: Chọn variant thông minh ──────────────────────────
        // Ưu tiên: khớp đúng color+size → color only → size only → bất kỳ còn hàng
        $selected = null;

        if ($color && $size) {
            $selected = $variants->first(fn ($v) => mb_stripos($v->color ?? '', $color) !== false &&
                mb_stripos($v->size ?? '', $size) !== false
            );
        }
        if (! $selected && $color) {
            $colorMatched = $variants->filter(fn ($v) => mb_stripos($v->color ?? '', $color) !== false);
            if ($colorMatched->isEmpty()) {
                // Màu không tồn tại trong hệ thống → báo rõ
                $availableColors = $variants->pluck('color')->filter()->unique()->values()->implode(', ');

                return [
                    'status' => 'color_not_found',
                    'message' => "Sản phẩm {$product->name} không có màu \"{$color}\". Các màu hiện có: {$availableColors}.",
                    'data' => [
                        'product_id' => $product->product_id,
                        'name' => $product->name,
                        'available_colors' => $variants->pluck('color')->filter()->unique()->values()->toArray(),
                    ],
                ];
            }
            // Có màu, thiếu size → chọn size bán chạy nhất (sold_count cao nhất variant)
            $selected = $size
                ? $colorMatched->first(fn ($v) => mb_stripos($v->size ?? '', $size) !== false) ?? $colorMatched->sortByDesc('sold_count')->first()
                : $colorMatched->sortByDesc('sold_count')->first();
        }
        if (! $selected && $size) {
            $sizeMatched = $variants->filter(fn ($v) => mb_stripos($v->size ?? '', $size) !== false);
            if ($sizeMatched->isEmpty()) {
                $availableSizes = $variants->pluck('size')->filter()->unique()->values()->implode(', ');

                return [
                    'status' => 'size_not_found',
                    'message' => "Sản phẩm {$product->name} không có size \"{$size}\". Các size hiện có: {$availableSizes}.",
                    'data' => [
                        'product_id' => $product->product_id,
                        'name' => $product->name,
                        'available_sizes' => $variants->pluck('size')->filter()->unique()->values()->toArray(),
                    ],
                ];
            }
            $selected = $sizeMatched->sortByDesc('sold_count')->first();
        }
        // Không có color/size → hỏi lại nếu nhiều lựa chọn, tự chọn nếu chỉ 1
        if (! $selected) {
            if ($variants->count() === 1) {
                $selected = $variants->first();
            } else {
                $availableColors = $variants->pluck('color')->filter()->unique()->values()->toArray();
                $availableSizes = $variants->pluck('size')->filter()->unique()->values()->toArray();

                return [
                    'status' => 'need_variant_info',
                    'message' => "Sản phẩm {$product->name} có nhiều lựa chọn. Bạn muốn màu và size nào?",
                    'data' => [
                        'product_id' => $product->product_id,
                        'name' => $product->name,
                        'thumbnail' => $product->mainImage?->image_url ?? $product->thumbnail_url,
                        'available_colors' => $availableColors,
                        'available_sizes' => $availableSizes,
                        'variants' => $variants->map(fn ($v) => $this->formatVariant($v))->values()->toArray(),
                    ],
                ];
            }
        }

        // ── Step 3: Validate tồn kho + giới hạn đơn ─────────────────
        if ($selected->stock < $quantity) {
            return $this->error(
                "{$product->name} ({$selected->color} / {$selected->size}) chỉ còn {$selected->stock} sản phẩm, bạn cần {$quantity}.",
                'insufficient_stock'
            );
        }

        $lineTotal = (float) $selected->price * $quantity;
        if ($lineTotal > self::QUICK_ORDER_MAX_AMOUNT) {
            return [
                'status' => 'over_limit',
                'message' => "Giá trị đơn hàng {$this->formatMoney($lineTotal)} vượt giới hạn đặt hàng tự động ({$this->formatMoney(self::QUICK_ORDER_MAX_AMOUNT)}). Vui lòng đặt qua trang giỏ hàng.",
                'data' => null,
            ];
        }

        // ── Step 4: Kiểm tra địa chỉ mặc định ────────────────────────
        $address = Address::where('user_id', $customer->user_id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();

        if (! $address) {
            return [
                'status' => 'no_address',
                'message' => 'Bạn chưa có địa chỉ giao hàng. Vui lòng thêm địa chỉ trong phần Tài khoản trước khi đặt hàng tự động.',
                'data' => null,
            ];
        }

        // ── Step 5: Add to cart ───────────────────────────────────────
        try {
            $this->cartService->addItem($customer->user_id, [
                'variant_id' => $selected->variant_id,
                'quantity' => $quantity,
            ]);
            $this->selectOnlyVariantForChatbotCheckout($customer->user_id, $selected->variant_id);
        } catch (\Throwable $e) {
            Log::error('Auto order add to cart failed', ['error' => $e->getMessage()]);

            return $this->error('Không thể thêm sản phẩm vào giỏ hàng. Vui lòng thử lại.', 'cart_error');
        }

        // ── Step 6: Prepare order ─────────────────────────────────────
        $paymentMethod = $customer->default_payment_method ?? 'cod';
        if (! in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
            $paymentMethod = 'cod';
        }

        $couponCode = isset($args['coupon_code']) && is_string($args['coupon_code'])
            ? mb_substr(strip_tags(trim($args['coupon_code'])), 0, 50)
            : null;

        $prepareResult = $this->prepareOrder([
            'address_id' => $address->address_id,
            'payment_method' => $paymentMethod,
            'coupon_applied' => $couponCode,
        ], $customer);

        if (($prepareResult['status'] ?? '') !== 'success') {
            return $prepareResult; // Trả error từ prepareOrder
        }

        $token = $prepareResult['data']['confirmation_token'] ?? null;
        if (! $token) {
            return $this->error('Không lấy được mã xác nhận đơn hàng. Vui lòng thử lại.', 'token_error');
        }

        // ── Step 7: Tự confirm order luôn ────────────────────────────
        $confirmResult = $this->confirmOrder(['confirmation_token' => $token], $customer, $request);

        if (($confirmResult['status'] ?? '') !== 'success') {
            return $confirmResult;
        }

        // Lưu default payment method cho lần sau
        if (! $customer->default_payment_method) {
            try {
                $customer->forceFill(['default_payment_method' => $paymentMethod])->save();
            } catch (\Throwable $e) {
                Log::warning('Auto order: failed to save payment method', ['error' => $e->getMessage()]);
            }
        }

        // ── Step 8: Format response thân thiện ───────────────────────
        $orderData = $confirmResult['data'] ?? [];
        $preview = $prepareResult['data'] ?? [];
        $totals = $preview['totals'] ?? [];

        return [
            'status' => 'auto_order_success',
            'message' => 'Đặt hàng tự động thành công!',
            'data' => [
                'order_id' => $orderData['order_id'] ?? ($orderData['data']['order_id'] ?? null),
                'order_code' => $orderData['order_code'] ?? ($orderData['data']['order_code'] ?? null),
                'product_name' => $product->name,
                'variant_label' => trim(($selected->color ?? '').' / '.($selected->size ?? ''), ' /'),
                'quantity' => $quantity,
                'unit_price' => $this->formatMoney($selected->price),
                'grand_total' => $totals['grand_total_formatted'] ?? $this->formatMoney($lineTotal),
                'shipping_fee' => $totals['shipping_fee_formatted'] ?? '—',
                'payment_method' => $this->paymentMethodLabel($paymentMethod),
                'address' => $this->formatAddress($address),
                'thumbnail' => $product->mainImage?->image_url ?? $product->thumbnail_url,
            ],
        ];
    }

    // ================================================================
    //  QUICK ORDER — Đặt hàng nhanh 1 bước
    // ================================================================

    private const QUICK_ORDER_MAX_AMOUNT = 5000000; // 5 triệu VNĐ

    /**
     * Quick Order qua Gemini function call.
     * Tìm sản phẩm, filter variant, auto-fill address + payment, tạo preview.
     */
    public function quickOrder(array $args, $customer = null): array
    {
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập tài khoản khách hàng để sử dụng đặt hàng nhanh.');
        }

        $args = $this->sanitizeSearchArgs($args);
        $keyword = $args['keyword'] ?? '';
        if (trim($keyword) === '') {
            return $this->error('Vui lòng cho biết bạn muốn mua sản phẩm gì.', 'missing_keyword');
        }

        $color = $args['color'] ?? null;
        $size = $args['size'] ?? null;
        $quantity = max(1, min((int) ($args['quantity'] ?? 1), 20));

        // Step 1: Tìm sản phẩm best match
        $product = Product::query()
            ->select(['product_id', 'category_id', 'name', 'slug', 'short_description', 'thumbnail_url', 'status', 'min_price', 'max_price', 'sold_count'])
            ->where('status', 'active')
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('short_description', 'LIKE', "%{$keyword}%");
            })
            ->with(['category:category_id,name', 'mainImage', 'variants' => function ($q) {
                $q->where('status', 'active')->where('stock', '>', 0)->orderBy('price', 'asc');
            }])
            ->orderByDesc('sold_count')
            ->first();

        if (! $product) {
            return [
                'status' => 'not_found',
                'message' => "Không tìm thấy sản phẩm nào phù hợp với \"{$keyword}\". Bạn có thể thử tìm với từ khóa khác.",
                'data' => null,
            ];
        }

        // Step 2: Filter variants theo color/size
        $variants = $product->variants;
        if ($variants->isEmpty()) {
            return $this->error('Sản phẩm '.$product->name.' hiện đã hết hàng.', 'out_of_stock');
        }

        $filteredVariants = $variants;
        if ($color) {
            $colorFiltered = $variants->filter(fn ($v) => mb_stripos($v->color ?? '', $color) !== false);
            if ($colorFiltered->isNotEmpty()) {
                $filteredVariants = $colorFiltered;
            }
        }
        if ($size) {
            $sizeFiltered = $filteredVariants->filter(fn ($v) => mb_stripos($v->size ?? '', $size) !== false);
            if ($sizeFiltered->isNotEmpty()) {
                $filteredVariants = $sizeFiltered;
            }
        }

        // Nếu nhiều variant phù hợp → trả về danh sách để user chọn
        if ($filteredVariants->count() > 1) {
            return [
                'status' => 'choose_variant',
                'message' => 'Sản phẩm '.$product->name.' có nhiều phiên bản. Vui lòng chọn màu/size.',
                'data' => [
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'thumbnail' => $product->mainImage?->image_url ?? $product->thumbnail_url,
                    'category' => $product->category?->name,
                    'quantity' => $quantity,
                    'coupon_code' => $args['coupon_code'] ?? null,
                    'variants' => $filteredVariants->map(fn ($v) => $this->formatVariant($v))->values()->toArray(),
                ],
            ];
        }

        // Chỉ 1 variant → auto-select
        $selectedVariant = $filteredVariants->first();

        return $this->executeQuickOrderWithVariant($product, $selectedVariant, $quantity, $args['coupon_code'] ?? null, $customer);
    }

    /**
     * Quick Order với variant đã chọn (gọi từ frontend sau khi user chọn variant).
     */
    public function quickOrderWithVariant(array $args, $customer = null): array
    {
        if (! $customer) {
            return $this->requiresLogin('Bạn cần đăng nhập để đặt hàng nhanh.');
        }

        $variantId = (int) ($args['variant_id'] ?? 0);
        $quantity = max(1, min((int) ($args['quantity'] ?? 1), 20));
        $couponCode = isset($args['coupon_code']) && is_string($args['coupon_code'])
            ? mb_substr(strip_tags(trim($args['coupon_code'])), 0, 50)
            : null;

        if (! $variantId) {
            return $this->error('Vui lòng chọn phiên bản sản phẩm.', 'missing_variant');
        }

        $variant = ProductVariant::with('product')
            ->where('variant_id', $variantId)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->first();

        if (! $variant || ! $variant->product || $variant->product->status !== 'active') {
            return $this->error('Phiên bản sản phẩm không khả dụng hoặc đã hết hàng.', 'invalid_variant');
        }

        if ($variant->stock < $quantity) {
            return $this->error('Sản phẩm '.$variant->product->name.' chỉ còn '.$variant->stock.' sản phẩm trong kho.', 'insufficient_stock');
        }

        return $this->executeQuickOrderWithVariant($variant->product, $variant, $quantity, $couponCode, $customer);
    }

    /**
     * Core logic: add to cart → auto-fill address/payment → validate limit → prepare order.
     */
    private function executeQuickOrderWithVariant(Product $product, ProductVariant $variant, int $quantity, ?string $couponCode, $customer): array
    {
        // Validate tồn kho
        if ($variant->stock < $quantity) {
            return $this->error('Sản phẩm '.$product->name.' chỉ còn '.$variant->stock.' sản phẩm.', 'insufficient_stock');
        }

        // Validate giới hạn giá trị đơn
        $lineTotal = (float) $variant->price * $quantity;
        if ($lineTotal > self::QUICK_ORDER_MAX_AMOUNT) {
            return [
                'status' => 'over_limit',
                'message' => 'Đơn hàng nhanh giới hạn tối đa '.$this->formatMoney(self::QUICK_ORDER_MAX_AMOUNT).'. '
                    .'Sản phẩm này có giá '.$this->formatMoney($lineTotal).'. '
                    .'Vui lòng đặt hàng qua giỏ hàng để không bị giới hạn.',
                'data' => [
                    'product_name' => $product->name,
                    'price' => $this->formatMoney($variant->price),
                    'quantity' => $quantity,
                    'total' => $this->formatMoney($lineTotal),
                    'limit' => $this->formatMoney(self::QUICK_ORDER_MAX_AMOUNT),
                ],
            ];
        }

        // Step 3: Check address
        $address = Address::where('user_id', $customer->user_id)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();

        if (! $address) {
            return [
                'status' => 'no_address',
                'message' => 'Bạn chưa có địa chỉ giao hàng. Vui lòng thêm địa chỉ trong tài khoản trước khi đặt hàng nhanh.',
                'data' => [
                    'product_name' => $product->name,
                    'variant' => $this->formatVariant($variant),
                ],
            ];
        }

        // Step 4: Payment method
        $paymentMethod = $customer->default_payment_method ?? 'cod';
        if (! in_array($paymentMethod, ['cod', 'bank_transfer'], true)) {
            $paymentMethod = 'cod';
        }

        // Step 5: Add to cart + select only this variant
        try {
            $this->cartService->addItem($customer->user_id, [
                'variant_id' => $variant->variant_id,
                'quantity' => $quantity,
            ]);
            $this->selectOnlyVariantForChatbotCheckout($customer->user_id, $variant->variant_id);
        } catch (\Throwable $e) {
            Log::error('Quick order add to cart failed', ['error' => $e->getMessage()]);

            return $this->error('Không thể thêm sản phẩm vào giỏ hàng. Vui lòng thử lại.', 'cart_error');
        }

        // Step 6: Prepare order (reuse existing logic)
        $prepareResult = $this->prepareOrder([
            'address_id' => $address->address_id,
            'payment_method' => $paymentMethod,
            'coupon_applied' => $couponCode,
        ], $customer);

        // Nếu prepare thành công, lưu default_payment_method cho lần sau
        if (($prepareResult['status'] ?? '') === 'success' && ! $customer->default_payment_method) {
            try {
                $customer->forceFill(['default_payment_method' => $paymentMethod])->save();
            } catch (\Throwable $e) {
                // Non-critical, không block flow
                Log::warning('Failed to save default payment method', ['error' => $e->getMessage()]);
            }
        }

        return $prepareResult;
    }

    private function sanitizeSearchArgs(array $args): array
    {
        $safe = [];
        foreach (['keyword', 'category', 'color', 'size'] as $field) {
            if (! empty($args[$field]) && is_string($args[$field])) {
                $safe[$field] = mb_substr(strip_tags(trim($args[$field])), 0, 60);
            }
        }

        if (! empty($args['categories']) && is_array($args['categories'])) {
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

        // on_sale: chỉ lấy sản phẩm đang giảm giá (compare_at_price > price)
        if (! empty($args['on_sale'])) {
            $safe['on_sale'] = true;
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
                if ($color) {
                    $q->where('color', 'LIKE', "%{$color}%");
                }
                if ($size) {
                    $q->where('size', 'LIKE', "%{$size}%");
                }
                $q->orderBy('price', 'asc');
            }]);

        if (! empty($args['keyword'])) {
            $keyword = trim((string) $args['keyword']);
            $keywordLen = mb_strlen($keyword);

            // Chia nhỏ từ khóa thành các token để tìm kiếm linh hoạt hơn
            // Ví dụ: "áo thun nam" -> ['áo', 'thun', 'nam']
            $tokens = array_filter(explode(' ', $keyword), fn ($t) => mb_strlen($t) > 0);

            $query->where(function ($q) use ($keyword, $keywordLen, $tokens) {
                // Cố gắng tìm chuỗi liền kề trước (ưu tiên)
                $q->where('name', 'LIKE', "%{$keyword}%");

                // Nếu không, tìm theo các token rời rạc trong name
                if (count($tokens) > 1) {
                    $q->orWhere(function ($subQ) use ($tokens) {
                        foreach ($tokens as $token) {
                            $subQ->where('name', 'LIKE', "%{$token}%");
                        }
                    });
                }

                // Tìm trong description nếu keyword dài
                if ($keywordLen > 3) {
                    $q->orWhere('short_description', 'LIKE', "%{$keyword}%");

                    if (count($tokens) > 1) {
                        $q->orWhere(function ($subQ) use ($tokens) {
                            foreach ($tokens as $token) {
                                $subQ->where('short_description', 'LIKE', "%{$token}%");
                            }
                        });
                    }
                }
            });
        }

        if (! empty($args['categories'])) {
            $categories = array_slice((array) $args['categories'], 0, 3);
            $categoryIds = Category::where(function ($q) use ($categories) {
                foreach ($categories as $categoryName) {
                    $q->orWhere('name', 'LIKE', '%'.trim((string) $categoryName).'%');
                }
            })->pluck('category_id');

            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->where(function ($q) use ($categories) {
                    foreach ($categories as $categoryName) {
                        $q->orWhere('name', 'LIKE', '%'.trim((string) $categoryName).'%');
                    }
                });
            }
        } elseif (! empty($args['category'])) {
            $categoryName = trim((string) $args['category']);
            $categoryIds = Category::where('name', 'LIKE', "%{$categoryName}%")->pluck('category_id');

            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->where(function ($q) use ($categoryName) {
                    $q->where('name', 'LIKE', "%{$categoryName}%")
                        ->orWhere('short_description', 'LIKE', "%{$categoryName}%");
                });
            }
        }

        if ($color || $size || ! empty($args['min_price']) || ! empty($args['max_price'])) {
            $query->whereHas('variants', function ($q) use ($color, $size, $args) {
                $q->where('status', 'active')->where('stock', '>', 0);
                if ($color) {
                    $q->where('color', 'LIKE', "%{$color}%");
                }
                if ($size) {
                    $q->where('size', 'LIKE', "%{$size}%");
                }
                if (! empty($args['min_price'])) {
                    $q->where('price', '>=', (float) $args['min_price']);
                }
                if (! empty($args['max_price'])) {
                    $q->where('price', '<=', (float) $args['max_price']);
                }
            });
        }

        // Lọc sản phẩm đang giảm giá (compare_at_price > price)
        if (! empty($args['on_sale'])) {
            $query->whereHas('variants', function ($q) {
                $q->where('status', 'active')
                    ->where('stock', '>', 0)
                    ->whereNotNull('compare_at_price')
                    ->whereColumn('compare_at_price', '>', 'price');
            });
        }

        $products = $query->orderByDesc('sold_count')->limit(6)->get();
        if ($products->isEmpty()) {
            $keyword = $args['keyword'] ?? $args['category'] ?? '';
            $hint = $keyword ? "Ocean Sport không có sản phẩm nào tên chứa \"{$keyword}\" trong hệ thống." : 'Không tìm thấy sản phẩm nào phù hợp.';

            return ['status' => 'no_results', 'message' => $hint.' Bạn có thể thử từ khoá khác hoặc xem danh mục sản phẩm bán chạy.', 'data' => []];
        }

        $data = $products->map(fn ($product) => $this->formatProductCard($product))->toArray();

        return [
            'status' => 'success',
            'count' => count($data),
            'message' => 'Tìm thấy '.count($data).' sản phẩm.',
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

        if (! $product) {
            return ['status' => 'not_found', 'message' => "Không tìm thấy sản phẩm \"{$identifier}\".", 'data' => null];
        }

        $thumbnail = $product->images->firstWhere('is_main', 1)?->image_url ?? $product->thumbnail_url;
        $variants = $product->variants->map(fn ($variant) => $this->formatVariant($variant))->toArray();

        $availableColors = $product->variants->pluck('color')->filter()->unique()->values()->toArray();
        $availableSizes = $product->variants->pluck('size')->filter()->unique()->values()->toArray();

        return [
            'status' => 'success',
            'message' => 'Đã tìm thấy chi tiết sản phẩm.',
            'data' => [
                'product_id' => $product->product_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'category' => $product->category?->name,
                'price_range' => $this->formatMoney($product->min_price).' - '.$this->formatMoney($product->max_price),
                'thumbnail' => $thumbnail,
                'available_colors' => $availableColors,
                'available_sizes' => $availableSizes,
                'variants' => $variants,
                'rating' => $product->rating_avg,
                'sold_count' => $product->sold_count,
            ],
        ];
    }

    private function selectOnlyVariantForChatbotCheckout(int $userId, int $variantId): void
    {
        $cart = $this->cartRepository->getActiveCart($userId);
        if (! $cart) {
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

        // Tính giá sale từ variant đầu tiên — lấy từ DB, không tin frontend
        $currentPrice = (float) ($variant?->price ?? $product->min_price ?? 0);
        $comparePrice = $variant?->compare_at_price ? (float) $variant->compare_at_price : null;
        $hasSale = $comparePrice && $comparePrice > $currentPrice;
        $salePercentage = $hasSale ? (int) round((1 - $currentPrice / $comparePrice) * 100) : 0;

        return [
            'product_id' => $product->product_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $this->formatMoney($currentPrice),
            'price_raw' => $currentPrice,
            'compare_at_price' => $hasSale ? $this->formatMoney($comparePrice) : null,
            'compare_at_price_raw' => $hasSale ? $comparePrice : null,
            'has_sale' => $hasSale,
            'sale_percentage' => $salePercentage,
            'thumbnail' => $thumbnail,
            'category' => $product->category?->name,
            'sold_count' => $product->sold_count,
            'available_colors' => $product->variants->pluck('color')->unique()->filter()->values()->toArray(),
            'available_sizes' => $product->variants->pluck('size')->unique()->filter()->values()->toArray(),
            'matched_variant' => $variant?->variant_name,
            'variant_id' => $variant?->variant_id,
            'stock' => $variant?->stock ?? 0,
            'variants' => $variants,
        ];
    }

    private function formatVariant(ProductVariant $variant): array
    {
        $comparePrice = $variant->compare_at_price ? (float) $variant->compare_at_price : null;
        $hasSale = $comparePrice && $comparePrice > (float) $variant->price;

        return [
            'variant_id' => $variant->variant_id,
            'variant_name' => $variant->variant_name,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => $this->formatMoney($variant->price),
            'price_raw' => (float) $variant->price,
            'compare_at_price' => $hasSale ? $this->formatMoney($comparePrice) : null,
            'has_sale' => $hasSale,
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
        if (! $phone) {
            return null;
        }
        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)).substr($phone, -4);
    }

    private function formatMoney(float|int|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, ',', '.').'đ';
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
