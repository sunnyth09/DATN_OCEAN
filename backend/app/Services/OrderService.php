<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCreatedAdmin;
use App\Exceptions\OrderException;
use App\Models\Address;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Repositories\AddressRepository;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductVariantRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected CartRepository $cartRepository,
        protected AddressRepository $addressRepository,
        protected ProductVariantRepository $variantRepository,
        protected CouponService $couponService,
        protected ComboService $comboService,
        protected ShippingService $shippingService,
        protected PaymentGatewayService $paymentGatewayService,
        protected AffiliateService $affiliateService,
        protected ReturnRequestService $returnRequestService,
        protected WalletService $walletService
    ) {}

    public function getUserOrders(int $userId, string $status = 'all')
    {
        $orders = $this->orderRepository->getUserOrders($userId, $status);

        $orders->getCollection()->transform(function ($order) {
            $order->is_reviewed = $order->items->every(
                fn ($item) => $item->comment !== null
            );
            $order->latest_return_request = $order->returnRequests
                ->sortByDesc('requested_at')
                ->first();
            $order->can_request_return = $this->returnRequestService->canUserRequestReturn($order);

            return $order;
        });

        return $orders;
    }

    public function getUserOrderDetail(int $userId, int $orderId)
    {
        $order = $this->orderRepository->getUserOrderDetail($userId, $orderId);

        if ($order) {
            $order->latest_return_request = $order->returnRequests
                ->sortByDesc('requested_at')
                ->first();
            $order->can_request_return = $this->returnRequestService->canUserRequestReturn($order);
            $order->return_window_days = $this->returnRequestService->getReturnWindowDays();
        }

        return $order;
    }

    public function getOrderIdByCode(int $userId, string $orderCode): ?int
    {
        $order = $this->orderRepository->findByCodeAndUser($userId, $orderCode);

        return $order?->order_id;
    }

    /**
     * Tra cứu Order object (kèm payment_status) theo order_code — dùng cho polling ở OrderSuccess.
     */
    public function getOrderByCode(int $userId, string $orderCode): ?Order
    {
        return $this->orderRepository->findByCodeAndUser($userId, $orderCode);
    }

    public function createOrder(int $userId, array $data, Request $request): array
    {
        try {
            $address = $this->resolveAddress($userId, $data);

            // Mua nhanh (Buy Now): đặt trực tiếp sản phẩm được truyền vào,
            // KHÔNG lấy từ giỏ hàng và KHÔNG ảnh hưởng tới giỏ hàng hiện có.
            $isDirectOrder = ! empty($data['items']) && is_array($data['items']);
            $cart = null;
            $isAbandonedCheckout = false;

            if ($isDirectOrder) {
                $cartItems = $this->buildDirectItems($data['items']);

                if ($cartItems->isEmpty()) {
                    throw new OrderException('Sản phẩm không hợp lệ!');
                }
            } else {
                $cart = $this->cartRepository->getActiveCart($userId);

                if (! $cart) {
                    throw new OrderException('Giỏ hàng trống!');
                }

                $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);

                if ($cartItems->isEmpty()) {
                    throw new OrderException('Vui lòng chọn sản phẩm để thanh toán!');
                }

                // Đơn đặt từ giỏ đã được gửi nhắc nhở bỏ quên → đánh dấu để cộng điểm khi hoàn tất
                $isAbandonedCheckout = (bool) $cart->is_abandoned_reminded;
            }

            $subtotal = $this->calculateSubtotalAndValidateStock($cartItems);

            $couponResult = $this->couponService->applyCoupon(
                $userId,
                $data['coupon_applied'] ?? null,
                $subtotal
            );

            if (! $couponResult['success']) {
                throw new OrderException($couponResult['message']);
            }

            $shippingFee = $this->shippingService->calculateShippingFee(
                $address,
                $subtotal,
                $couponResult['coupon'],
                $this->shippingService->calculateWeight($cartItems)
            );

            $discountAmount = $couponResult['discount_amount'];
            $couponId = $couponResult['coupon']?->id;

            // Áp dụng Combo/Bundle (Flash Sale combo + Auto-apply Combo Voucher)
            $comboResult = $this->comboService->applyAllCombos($userId, $cartItems, $subtotal);
            $comboDiscount = $comboResult['discount_amount'];

            // === TÍNH TOÁN ĐIỂM THƯỞNG ===
            $rewardPointsUsed = (int) ($data['reward_points_used'] ?? 0);
            $rewardDiscount = 0;
            $user = null;
            if ($rewardPointsUsed > 0) {
                $user = User::find($userId);
                if (! $user) {
                    throw new OrderException('Không tìm thấy người dùng!');
                }

                $preview = app(LoyaltyService::class)->previewBurn($userId, $rewardPointsUsed, $subtotal);

                if (! $preview['eligible']) {
                    throw new OrderException($preview['message']);
                }

                // Nếu user cố ý truyền sai số lượng vượt quá cho phép, previewBurn sẽ trả về actual_points nhỏ hơn.
                // Ở đây ta có thể update lại $rewardPointsUsed thành actual_points
                $rewardPointsUsed = $preview['points_to_use'];
                $rewardDiscount = $preview['discount_amount'];
                $discountAmount += $rewardDiscount;
            }

            $grandTotal = max(0, $subtotal + $shippingFee - $discountAmount - $comboDiscount);

            // ── Wallet Discount ──────────────────────────────────────────
            $useWallet = ! empty($data['use_wallet']);
            $walletDepositUsed = 0;
            $walletCommissionUsed = 0;
            $walletTotalDiscount = 0;

            if ($useWallet && $grandTotal > 0) {
                $requestedWalletAmount = (float) ($data['wallet_amount'] ?? 0);

                if ($requestedWalletAmount > 0) {
                    // Preview để validate giới hạn
                    $preview = $this->walletService->previewDiscount($userId, $grandTotal);
                    $walletTotalDiscount = min($requestedWalletAmount, $preview['max_total_discount']);
                }
            }

            // Xác định đơn hàng có từ giỏ hàng bỏ quên hay không
            $isAbandonedCheckout = ! $isDirectOrder && isset($cart) && $cart->is_abandoned_reminded;

            // Tính grand_total sau wallet discount
            $paymentMethod = $data['payment_method'];
            $grandTotalAfterWallet = max(0, $grandTotal - $walletTotalDiscount);

            // Nếu ví trả hết → payment_method = 'wallet'
            if ($grandTotalAfterWallet == 0 && $walletTotalDiscount > 0) {
                $paymentMethod = 'wallet';
            }

            $result = DB::transaction(function () use (
                $userId,
                $user,
                $data,
                $request,
                $address,
                $cartItems,
                $subtotal,
                $discountAmount,
                $comboDiscount,
                $comboResult,
                $shippingFee,
                $grandTotalAfterWallet,
                $couponId,
                $couponResult,
                $useWallet,
                $walletTotalDiscount,
                &$walletDepositUsed,
                &$walletCommissionUsed,
                $paymentMethod,
                $isAbandonedCheckout,
                $isDirectOrder,
                $rewardPointsUsed
            ) {
                $this->lockAndValidateStock($cartItems, $subtotal);

                // Lưu địa chỉ mới nếu cần (lưu TRONG transaction để rollback nếu đơn lỗi)
                if (isset($address->needs_saving) && $address->needs_saving) {
                    if (Address::where('user_id', $userId)->count() < Address::MAX_PER_USER) {
                        // Xóa flag tạm trước khi save để tránh lỗi "Unknown column 'needs_saving'"
                        unset($address->needs_saving);
                        $address->save();
                    } else {
                        unset($address->needs_saving);
                    }
                }

                // Áp dụng wallet discount (trong transaction để đảm bảo atomic)
                if ($useWallet && $walletTotalDiscount > 0) {
                    $walletResult = $this->walletService->applyOrderDiscount(
                        $userId,
                        $walletTotalDiscount,
                        0 // orderId chưa có, sẽ update reference sau
                    );
                    $walletDepositUsed = $walletResult['deposit_used'];
                    $walletCommissionUsed = $walletResult['commission_used'];
                    $walletTotalDiscount = $walletResult['total_discount'];
                }

                $order = $this->orderRepository->create([
                    'order_code' => $this->generateOrderCode(),
                    'user_id' => $userId,
                    'address_id' => $address->address_id, // Có thể null nếu user vượt quá MAX_PER_USER
                    'promotion_id' => $couponId,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->phone,
                    'email' => $data['email'] ?? null,
                    'shipping_address' => $this->makeFullAddress($address),
                    'province_code' => $address->province_code ?? null,
                    'district_code' => $address->district_code ?? null,
                    'ward_code' => $address->ward_code ?? null,
                    'note' => $data['note'] ?? null,
                    'payment_method' => $paymentMethod,
                    'payment_status' => ($grandTotalAfterWallet == 0 || $data['payment_method'] === 'wallet') ? 'paid' : 'unpaid',
                    'fulfillment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'wallet_deposit_discount' => $walletDepositUsed,
                    'wallet_commission_discount' => $walletCommissionUsed,
                    'shipping_fee' => $shippingFee,
                    'grand_total' => $grandTotalAfterWallet,
                    'combo_discount' => $comboDiscount,
                    'wallet_spent' => $data['payment_method'] === 'wallet' ? $grandTotalAfterWallet : 0.00,
                    'is_abandoned_checkout' => $isAbandonedCheckout,
                ]);

                foreach ($cartItems as $cartItem) {
                    $this->orderRepository->createItem([
                        'order_id' => $order->order_id,
                        'product_id' => $cartItem->variant->product_id,
                        'variant_id' => $cartItem->variant_id,
                        'product_name' => $cartItem->variant->product->name,
                        'variant_name' => $cartItem->variant->variant_name,
                        'sku' => $cartItem->variant->sku,
                        'color' => $cartItem->variant->color,
                        'size' => $cartItem->variant->size,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $this->resolveEffectivePrice($cartItem->variant),
                        'line_total' => $this->resolveEffectivePrice($cartItem->variant) * $cartItem->quantity,
                    ]);

                    $this->variantRepository->decrementStock(
                        $cartItem->variant_id,
                        $cartItem->quantity
                    );

                    if ($cartItem->variant->product_id) {
                        Product::where('product_id', $cartItem->variant->product_id)
                            ->increment('sold_count', $cartItem->quantity);

                        // Đồng bộ số lượng đã bán vào Flash Sale nếu sản phẩm đang có chiến dịch active
                        $this->syncFlashSaleSoldCount($cartItem->variant->product_id, $cartItem->quantity);
                    }
                }

                Cache::tags(['products:best-selling'])->flush();

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'new_status' => OrderStatus::PENDING->value,
                    'note' => 'Khách hàng đặt đơn hàng mới',
                ]);

                if ($couponId) {
                    // Tiêu thụ coupon atomic (conditional UPDATE) để chống race/over-redemption.
                    // Ném OrderException → rollback toàn bộ đơn nếu đã hết lượt.
                    $this->couponService->consumeCoupon(
                        $userId,
                        $couponResult['coupon']
                    );
                }

                // Trừ điểm thưởng
                if ($rewardPointsUsed > 0 && $user) {
                    app(LoyaltyService::class)->burnPoints(
                        $user,
                        $rewardPointsUsed,
                        $order
                    );
                }

                // Đánh dấu đã dùng combo vouchers (auto-apply)
                if (! empty($comboResult['applied_combo_vouchers'])) {
                    $this->comboService->markVouchersAsUsed(
                        $comboResult['applied_combo_vouchers'],
                        $userId
                    );
                }

                // Xóa item khỏi giỏ hàng nếu là đơn đặt từ giỏ hàng
                $cartItemIds = $cartItems->pluck('cart_item_id')->filter()->values()->toArray();
                if (! empty($cartItemIds)) {
                    $this->cartRepository->deleteItems($cartItemIds);
                }

                // Reset trạng thái giỏ hàng bỏ quên
                if ($isAbandonedCheckout && ! $isDirectOrder) {
                    $activeCart = $this->cartRepository->getActiveCart($userId);
                    if ($activeCart) {
                        $activeCart->update(['is_abandoned_reminded' => false]);
                    }
                }

                // Thực hiện trừ tiền từ ví nếu thanh toán bằng ví.
                // Số tiền cần trả là grand_total SAU khi đã trừ wallet-discount (nếu có),
                // tránh trừ ví hai lần khi user vừa dùng ví giảm giá vừa chọn thanh toán ví.
                if ($data['payment_method'] === 'wallet' && $grandTotalAfterWallet > 0) {
                    $this->walletService->spend(
                        $userId,
                        $grandTotalAfterWallet,
                        "Thanh toán đơn hàng #{$order->order_code}",
                        $order->order_id,
                        Order::class
                    );
                }

                return [
                    'status_code' => 200,
                    '_order' => $order,
                    '_payment_method' => $data['payment_method'],
                    '_request' => $request,
                ];
            });

            // Các side-effect SAU transaction: đơn hàng, tồn kho, ví, điểm ĐÃ commit.
            // Bọc riêng để lỗi ở đây KHÔNG khiến trả 500 "tạo đơn thất bại" — nếu không
            // user sẽ retry và tạo đơn trùng (double stock/points).
            if (isset($result['_order'])) {
                $order = $result['_order'];

                $paymentResult = $this->paymentGatewayService->handlePayment(
                    $order,
                    $result['_payment_method'],
                    $result['_request']
                );

                $this->dispatchOrderCreatedEvent($order);

                try {
                    $this->affiliateService->createConversionFromOrder(
                        $order,
                        $data['referral_code'] ?? null
                    );
                } catch (\Throwable $e) {
                    Log::error('Affiliate conversion failed (đơn đã được tạo thành công): '.$e->getMessage());
                }

                unset($result['_order']);
                unset($result['_payment_method']);
                unset($result['_request']);

                if ($paymentResult['type'] === 'redirect') {
                    $result['body'] = $paymentResult['body'];
                } else {
                    $result['body'] = [
                        'status' => 'success',
                        'message' => 'Đặt hàng thành công!',
                        'data' => [
                            'order_id' => $order->order_id,
                            'order_code' => $order->order_code,
                            'grand_total' => $order->grand_total,
                        ],
                    ];
                }
            }

            try {
                Cache::flush();
            } catch (\Throwable $e) {
                Log::warning('Cache flush failed sau khi tạo đơn: '.$e->getMessage());
            }

            return $result;
        } catch (OrderException $e) {
            // Lỗi nghiệp vụ (hết hàng, địa chỉ sai...) → trả message gốc cho user
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Log::error('Order creation failed: '.$e->getMessage());

            return $this->error(
                'Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại sau.',
                500
            );
        }
    }

    public function createGuestOrder(array $data, Request $request): array
    {
        try {
            if (empty($data['items'])) {
                return $this->error('Giỏ hàng trống!', 400);
            }

            $items = collect($data['items']);
            $variantIds = $items->pluck('variant_id')->toArray();
            $variants = ProductVariant::whereIn('variant_id', $variantIds)
                ->with('product')
                ->get()
                ->keyBy('variant_id');

            $cartItems = $items->map(function ($item) use ($variants) {
                $variant = $variants->get($item['variant_id']);
                if (! $variant) {
                    return null;
                }

                return (object) [
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'variant' => $variant,
                ];
            })->filter()->values();

            if ($cartItems->isEmpty()) {
                return $this->error('Vui lòng chọn sản phẩm để thanh toán!', 400);
            }

            $subtotal = $this->calculateSubtotalAndValidateStock($cartItems);

            $couponResult = [
                'success' => true,
                'coupon' => null,
                'discount_amount' => 0,
            ];

            $addressObj = (object) [
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'province' => $data['province'],
                // district: cấp hành chính đã bị bỏ sau sáp nhập 2025 — client mới
                // không gửi field này nữa, giữ lại chỉ để tương thích payload cũ.
                'district' => $data['district'] ?? null,
                'ward' => $data['ward'],
                'address_line' => $data['address_line'],
                'province_code' => $data['province_code'] ?? null,
                'district_code' => $data['district_code'] ?? null,
                'ward_code' => $data['ward_code'] ?? null,
            ];

            $shippingFee = $this->shippingService->calculateShippingFee(
                $addressObj,
                $subtotal,
                $couponResult['coupon'],
                $this->shippingService->calculateWeight($cartItems)
            );

            $discountAmount = $couponResult['discount_amount'];
            $couponId = $couponResult['coupon']?->id;

            $comboResult = $this->comboService->applyAllCombos(0, $cartItems, $subtotal);
            $comboDiscount = $comboResult['discount_amount'];

            $grandTotal = max(0, $subtotal + $shippingFee - $discountAmount - $comboDiscount);

            $result = DB::transaction(function () use (
                $data,
                $request,
                $addressObj,
                $cartItems,
                $subtotal,
                $discountAmount,
                $comboDiscount,
                $shippingFee,
                $grandTotal,
                $couponId,
                $couponResult
            ) {
                $this->lockAndValidateStock($cartItems, $subtotal);

                $order = $this->orderRepository->create([
                    'order_code' => $this->generateOrderCode(),
                    'user_id' => null,
                    'address_id' => null,
                    'promotion_id' => $couponId,
                    'recipient_name' => $addressObj->recipient_name,
                    'recipient_phone' => $addressObj->phone,
                    'email' => $data['email'] ?? null,
                    'shipping_address' => $this->makeFullAddress($addressObj),
                    'province_code' => $addressObj->province_code ?? null,
                    'district_code' => $addressObj->district_code ?? null,
                    'ward_code' => $addressObj->ward_code ?? null,
                    'note' => $data['note'] ?? null,
                    'payment_method' => $data['payment_method'],
                    'payment_status' => 'unpaid',
                    'fulfillment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => $shippingFee,
                    'grand_total' => $grandTotal,
                    'combo_discount' => $comboDiscount,
                ]);

                foreach ($cartItems as $cartItem) {
                    $this->orderRepository->createItem([
                        'order_id' => $order->order_id,
                        'product_id' => $cartItem->variant->product_id,
                        'variant_id' => $cartItem->variant_id,
                        'product_name' => $cartItem->variant->product->name,
                        'variant_name' => $cartItem->variant->variant_name,
                        'sku' => $cartItem->variant->sku,
                        'color' => $cartItem->variant->color,
                        'size' => $cartItem->variant->size,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $this->resolveEffectivePrice($cartItem->variant),
                        'line_total' => $this->resolveEffectivePrice($cartItem->variant) * $cartItem->quantity,
                    ]);

                    $this->variantRepository->decrementStock(
                        $cartItem->variant_id,
                        $cartItem->quantity
                    );

                    if ($cartItem->variant->product_id) {
                        Product::where('product_id', $cartItem->variant->product_id)
                            ->increment('sold_count', $cartItem->quantity);

                        // Đồng bộ số lượng đã bán vào Flash Sale nếu sản phẩm đang có chiến dịch active
                        $this->syncFlashSaleSoldCount($cartItem->variant->product_id, $cartItem->quantity);
                    }
                }

                Cache::tags(['products:best-selling'])->flush();

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'new_status' => OrderStatus::PENDING->value,
                    'note' => 'Khách vãng lai đặt đơn hàng mới',
                ]);

                if ($couponId) {
                    $this->couponService->markCouponAsUsed(
                        0,
                        $couponResult['coupon']
                    );
                }

                return [
                    'status_code' => 200,
                    '_order' => $order,
                    '_payment_method' => $data['payment_method'],
                    '_request' => $request,
                ];
            });

            if (isset($result['_order'])) {
                $order = $result['_order'];

                $paymentResult = $this->paymentGatewayService->handlePayment(
                    $order,
                    $result['_payment_method'],
                    $result['_request']
                );

                $this->dispatchOrderCreatedEvent($order);

                try {
                    $this->affiliateService->createConversionFromOrder(
                        $order,
                        $data['referral_code'] ?? null
                    );
                } catch (\Throwable $e) {
                    Log::error('Guest Affiliate conversion failed: '.$e->getMessage());
                }

                unset($result['_order']);
                unset($result['_payment_method']);
                unset($result['_request']);

                if ($paymentResult['type'] === 'redirect') {
                    $result['body'] = $paymentResult['body'];
                } else {
                    $result['body'] = [
                        'status' => 'success',
                        'message' => 'Đặt hàng thành công!',
                        'data' => [
                            'order_id' => $order->order_id,
                            'order_code' => $order->order_code,
                            'grand_total' => $order->grand_total,
                        ],
                    ];
                }
            }

            Cache::flush();

            return $result;
        } catch (OrderException $e) {
            // Lỗi nghiệp vụ (hết hàng, coupon sai...) → trả message gốc cho user
            return $this->error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Log::error('Guest Order creation failed: '.$e->getMessage());

            return $this->error(
                'Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại sau.',
                500
            );
        }
    }

    public function cancelOrder(int $userId, int $orderId, string $reason): array
    {
        try {
            DB::transaction(function () use ($userId, $orderId, $reason) {
                // Lock dòng đơn hàng và re-check trạng thái BÊN TRONG transaction để chống
                // race: 2 request hủy đồng thời không thể cùng hoàn ví/tồn kho/điểm hai lần.
                $order = Order::where('user_id', $userId)
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    throw new OrderException('Không tìm thấy đơn hàng!');
                }

                if ($order->fulfillment_status !== OrderStatus::PENDING->value) {
                    throw new OrderException('Bạn chỉ có thể hủy đơn hàng khi đang chờ xác nhận!');
                }

                $this->orderRepository->cancel($order, $reason);

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'old_status' => OrderStatus::PENDING->value,
                    'new_status' => OrderStatus::CANCELLED->value,
                    'note' => 'Khách hàng hủy đơn: '.$reason,
                ]);

                $items = $this->orderRepository->getOrderItems($order->order_id);

                $this->variantRepository->restoreStockFromOrderItems($items);

                // ── Hoàn ví nếu đơn có dùng ví GIẢM GIÁ ──
                $walletDeposit = (float) ($order->wallet_deposit_discount ?? 0);
                $walletCommission = (float) ($order->wallet_commission_discount ?? 0);

                if (($walletDeposit + $walletCommission) > 0 && $order->user_id) {
                    $this->walletService->reverseOrderDiscount(
                        $order->user_id,
                        $walletDeposit,
                        $walletCommission,
                        $order->order_id
                    );
                }

                // ── Hoàn ví nếu đơn THANH TOÁN TOÀN PHẦN bằng ví (wallet_spent) ──
                // Đây là cột độc lập với wallet discount ở trên; đơn có thể có cả hai.
                // Chỉ hoàn khi đơn đã ở trạng thái paid để tránh hoàn cho đơn chưa trừ tiền.
                $walletSpent = (float) ($order->wallet_spent ?? 0);
                if ($walletSpent > 0 && $order->user_id && $order->payment_status === 'paid') {
                    $this->walletService->refund(
                        $order->user_id,
                        $walletSpent,
                        "Hoàn tiền hủy đơn hàng #{$order->order_code}",
                        $order->order_id,
                        Order::class
                    );
                }

                // ── Hoàn điểm thưởng nếu có dùng ──
                if ($order->user_id) {
                    $user = User::find($order->user_id);
                    if ($user) {
                        app(LoyaltyService::class)->refundPoints($user, $order);
                    }
                }
            });

            Cache::flush();

            return [
                'status_code' => 200,
                'body' => [
                    'status' => 'success',
                    'message' => 'Đã hủy đơn hàng thành công!',
                ],
            ];
        } catch (OrderException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Order cancel error: '.$e->getMessage());

            return $this->error('Lỗi khi hủy đơn.', 500);
        }
    }

    private function resolveAddress(int $userId, array $data)
    {
        if (! empty($data['address_id'])) {
            $address = $this->addressRepository->findUserAddress(
                $userId,
                $data['address_id']
            );

            if (! $address) {
                throw new OrderException('Địa chỉ không hợp lệ!');
            }

            return $address;
        }

        $addressData = [
            'user_id' => $userId,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'province' => $data['province'],
            // district: cấp hành chính đã bỏ sau sáp nhập 2025 — client mới không gửi.
            'district' => $data['district'] ?? null,
            'ward' => $data['ward'],
            'address_line' => $data['address_line'],
            'province_code' => $data['province_code'] ?? null,
            'district_code' => $data['district_code'] ?? null,
            'ward_code' => $data['ward_code'] ?? null,
            'is_default' => false,
        ];

        // Create an unsaved Address model instance
        $address = new Address($addressData);
        $address->needs_saving = true;

        return $address;
    }

    /**
     * Dựng danh sách item cho đơn "Mua nhanh" (Buy Now) từ mảng items truyền vào.
     * Trả về collection có cùng hình dạng với cart items (object có ->variant).
     */
    private function buildDirectItems(array $items)
    {
        $items = collect($items);
        $variantIds = $items->pluck('variant_id')->toArray();

        $variants = ProductVariant::whereIn('variant_id', $variantIds)
            ->with('product')
            ->get()
            ->keyBy('variant_id');

        return $items->map(function ($item) use ($variants) {
            $variant = $variants->get($item['variant_id']);
            if (! $variant) {
                return null;
            }

            return (object) [
                'variant_id' => $item['variant_id'],
                'quantity' => (int) $item['quantity'],
                'variant' => $variant,
            ];
        })->filter()->values();
    }

    private function calculateSubtotalAndValidateStock($cartItems): float
    {
        $subtotal = 0;

        foreach ($cartItems as $item) {
            if (! $item->variant || ! $item->variant->product || $item->variant->product->trashed() || $item->variant->status !== 'active') {
                $productName = $item->variant && $item->variant->product ? $item->variant->product->name : 'Một số sản phẩm';
                throw new OrderException("Sản phẩm {$productName} đã ngừng kinh doanh hoặc tạm ẩn. Vui lòng quay lại giỏ hàng và xóa khỏi danh sách.");
            }

            if ($item->variant->stock < $item->quantity) {
                throw new OrderException(
                    'Sản phẩm '.$item->variant->product->name.' không đủ tồn kho!'
                );
            }

            $subtotal += $item->variant->effective_price * $item->quantity;
        }

        return $subtotal;
    }

    private function lockAndValidateStock($cartItems, float $expectedSubtotal): void
    {
        $variantIds = $cartItems->pluck('variant_id')->toArray();

        $lockedVariants = $this->variantRepository->lockVariants($variantIds);

        $actualSubtotal = 0;

        foreach ($cartItems as $cartItem) {
            $lockedVariant = $lockedVariants[$cartItem->variant_id] ?? null;

            if (! $lockedVariant || $lockedVariant->stock < $cartItem->quantity) {
                throw new OrderException(
                    'Sản phẩm '.$cartItem->variant->product->name.' đã hết hàng khi bạn đặt mua!'
                );
            }

            $actualSubtotal += $lockedVariant->effective_price * $cartItem->quantity;
        }

        // Re-validate price
        if (abs($actualSubtotal - $expectedSubtotal) > 0.01) {
            throw new OrderException('Giá của một số sản phẩm đã thay đổi trong lúc thanh toán. Vui lòng tải lại giỏ hàng!');
        }
    }

    private function makeFullAddress($address): string
    {
        return implode(', ', array_unique(array_filter([
            $address->address_line,
            $address->ward,
            $address->district ?? null,
            $address->province,
        ])));
    }

    private function generateOrderCode(): string
    {
        return 'ORD'.strtoupper(substr(Str::uuid()->toString(), 0, 8)).rand(100, 999);
    }

    private function dispatchOrderCreatedEvent($order): void
    {
        try {
            event(new OrderCreatedAdmin($order));

            // Notify Customer via Queue to optimize performance
            if ($order->user) {
                Notification::send($order->user, new SystemNotification(
                    'Đặt hàng thành công',
                    'Đơn hàng '.$order->order_code.' của bạn đã được đặt thành công.',
                    '/profile/orders/'.$order->order_id,
                    'bell'
                ));
            }

            // Notify Admins
            $admins = User::whereIn('role', ['admin', 'seller'])->get();
            Notification::send($admins, new SystemNotification(
                'Đơn hàng mới',
                'Khách hàng vừa đặt đơn hàng '.$order->order_code,
                '/admin/order/'.$order->order_id,
                'order',
                [
                    'payment_status' => $order->payment_status,
                    'fulfillment_status' => $order->fulfillment_status,
                ]
            ));
        } catch (\Exception $e) {
            Log::error('Realtime event dispatch failed: '.$e->getMessage());
        }
    }

    private function error(string $message, int $statusCode): array
    {
        return [
            'status_code' => $statusCode,
            'body' => [
                'status' => 'error',
                'message' => $message,
            ],
        ];
    }

    /**
     * Trả về giá thực tế khi đặt hàng cho một variant.
     *
     * Logic:
     *  - Nếu sản phẩm đang có Flash Sale active VÀ còn quota (remaining > 0)
     *    → trả về effective_price (giá Flash Sale)
     *  - Nếu Flash Sale đã hết quota (remaining <= 0)
     *    → trả về giá gốc variant->price (không cho mua giá Flash Sale)
     *  - Nếu không có Flash Sale → trả về effective_price bình thường
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return float
     */
    private function resolveEffectivePrice(ProductVariant $variant): float
    {
        return (float) $variant->effective_price;
    }

    /**
     * Đồng bộ số lượng đã bán vào Flash Sale khi đặt hàng qua luồng thường.
     *
     * Khi người dùng mua sản phẩm qua checkout thông thường (không phải luồng
     * Flash Sale riêng), hệ thống cần kiểm tra xem sản phẩm có đang nằm trong
     * chiến dịch Flash Sale đang active hay không. Nếu có:
     *   - Tăng cột `sold` trong bảng `flash_sale_items` để UI hiển thị đúng.
     *   - Trừ Redis stock key tương ứng để thanh progress bar đồng bộ.
     *
     * Phương thức được thiết kế fire-and-forget (không throw), lỗi chỉ được
     * ghi vào log để không ảnh hưởng đến luồng tạo đơn chính.
     */
    private function syncFlashSaleSoldCount(int $productId, int $quantity): void
    {
        try {
            $now = now();

            // Tìm Flash Sale item đang active cho sản phẩm này
            $flashSaleItem = FlashSaleItem::where('product_id', $productId)
                ->whereHas('flashSale', function ($q) use ($now) {
                    $q->where('status', 'active')
                        ->where('start_time', '<=', $now)
                        ->where('end_time', '>=', $now);
                })
                ->with('flashSale')
                ->lockForUpdate()
                ->first();

            if (! $flashSaleItem) {
                return; // Sản phẩm không thuộc Flash Sale nào đang active
            }

            $flashSale = $flashSaleItem->flashSale;

            // Tăng sold trong flash_sale_items (không vượt quá campaign_stock)
            $maxAdditional = max(0, $flashSaleItem->campaign_stock - $flashSaleItem->sold);
            $actualIncrement = min($quantity, $maxAdditional);

            if ($actualIncrement > 0) {
                $flashSaleItem->increment('sold', $actualIncrement);

                // Đồng bộ Redis stock key nếu đang tồn tại
                $stockKey = "flash_sale_{$flashSale->id}_product_{$productId}_stock";
                if (\Illuminate\Support\Facades\Redis::exists($stockKey)) {
                    $remaining = \Illuminate\Support\Facades\Redis::decrby($stockKey, $actualIncrement);
                    // Đảm bảo Redis không âm (phòng trường hợp race condition)
                    if ($remaining < 0) {
                        \Illuminate\Support\Facades\Redis::set($stockKey, 0);
                    }
                }

                Log::info("[OrderService] Đã đồng bộ Flash Sale sold_count: product #{$productId}, +{$actualIncrement} sold, flash_sale #{$flashSale->id}");
            }
        } catch (\Throwable $e) {
            // Fire-and-forget: không throw để không ảnh hưởng luồng tạo đơn
            Log::error("[OrderService] syncFlashSaleSoldCount thất bại cho product #{$productId}: {$e->getMessage()}");
        }
    }
}
