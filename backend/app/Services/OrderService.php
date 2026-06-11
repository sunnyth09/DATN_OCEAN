<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Repositories\AddressRepository;
use App\Repositories\ProductVariantRepository;
use App\Services\ComboService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        protected ReturnRequestService $returnRequestService
    ) {}

    public function getUserOrders(int $userId, string $status = 'all')
    {
        $orders = $this->orderRepository->getUserOrders($userId, $status);

        $orders->getCollection()->transform(function ($order) {
            $order->is_reviewed = $order->items->every(
                fn($item) => $item->comment !== null
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

    public function createOrder(int $userId, array $data, Request $request): array
    {
        try {
            $address = $this->resolveAddress($userId, $data);

            $cart = $this->cartRepository->getActiveCart($userId);

            if (!$cart) {
                return $this->error('Giỏ hàng trống!', 400);
            }

            $cartItems = $this->cartRepository->getSelectedCartItems($cart->cart_id);

            if ($cartItems->isEmpty()) {
                return $this->error('Vui lòng chọn sản phẩm để thanh toán!', 400);
            }

            $subtotal = $this->calculateSubtotalAndValidateStock($cartItems);

            $couponResult = $this->couponService->applyCoupon(
                $userId,
                $data['coupon_applied'] ?? null,
                $subtotal
            );

            if (!$couponResult['success']) {
                return $this->error($couponResult['message'], 400);
            }

            $shippingFee = $this->shippingService->calculateShippingFee(
                $address,
                $subtotal,
                $couponResult['coupon']
            );

            $discountAmount = $couponResult['discount_amount'];
            $couponId = $couponResult['coupon']?->id;

            // Áp dụng Combo/Bundle (Flash Sale combo + Auto-apply Combo Voucher)
            $comboResult   = $this->comboService->applyAllCombos($userId, $cartItems, $subtotal);
            $comboDiscount = $comboResult['discount_amount'];

            $grandTotal = max(0, $subtotal + $shippingFee - $discountAmount - $comboDiscount);

            $result = DB::transaction(function () use (
                $userId,
                $data,
                $request,
                $address,
                $cartItems,
                $subtotal,
                $discountAmount,
                $comboDiscount,
                $comboResult,
                $shippingFee,
                $grandTotal,
                $couponId,
                $couponResult
            ) {
                $this->lockAndValidateStock($cartItems);

                $order = $this->orderRepository->create([
                    'order_code' => $this->generateOrderCode(),
                    'user_id' => $userId,
                    'address_id' => $address->address_id,
                    'promotion_id' => $couponId,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->phone,
                    'shipping_address' => $this->makeFullAddress($address),
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
                        'unit_price' => $cartItem->variant->price,
                        'line_total' => $cartItem->variant->price * $cartItem->quantity,
                    ]);

                    $this->variantRepository->decrementStock(
                        $cartItem->variant_id,
                        $cartItem->quantity
                    );
                }

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'new_status' => OrderStatus::PENDING->value,
                    'note' => 'Khách hàng đặt đơn hàng mới',
                ]);

                if ($couponId) {
                    $this->couponService->markCouponAsUsed(
                        $userId,
                        $couponResult['coupon']
                    );
                }

                // Đánh dấu đã dùng combo vouchers (auto-apply)
                if (!empty($comboResult['applied_combo_vouchers'])) {
                    $this->comboService->markVouchersAsUsed(
                        $comboResult['applied_combo_vouchers'],
                        $userId
                    );
                }

                $this->cartRepository->deleteItems(
                    $cartItems->pluck('cart_item_id')->toArray()
                );

                $paymentResult = $this->paymentGatewayService->handlePayment(
                    $order,
                    $data['payment_method'],
                    $request
                );

                if ($paymentResult['type'] === 'redirect') {
                    return [
                        'status_code' => 200,
                        'body' => $paymentResult['body'],
                        '_order' => $order,
                    ];
                }

                $this->dispatchOrderCreatedEvent($order);

                return [
                    'status_code' => 200,
                    'body' => [
                        'status' => 'success',
                        'message' => 'Đặt hàng thành công!',
                        'data' => [
                            'order_code' => $order->order_code,
                            'grand_total' => $order->grand_total,
                        ],
                    ],
                    '_order' => $order,
                ];
            });

            // Ghi nhận affiliate conversion SAU transaction thành công
            if (isset($result['_order'])) {
                $this->affiliateService->createConversionFromOrder(
                    $result['_order'],
                    $data['referral_code'] ?? null
                );
                unset($result['_order']); // Không trả _order ra response
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage());

            return $this->error(
                'Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại sau.',
                500
            );
        }
    }

    public function cancelOrder(int $userId, int $orderId, string $reason): array
    {
        $order = $this->orderRepository->findUserOrder($userId, $orderId);

        if (!$order) {
            return $this->error('Không tìm thấy đơn hàng!', 404);
        }

        if ($order->fulfillment_status !== OrderStatus::PENDING->value) {
            return $this->error(
                'Bạn chỉ có thể hủy đơn hàng khi đang chờ xác nhận!',
                400
            );
        }

        try {
            DB::transaction(function () use ($order, $reason) {
                $this->orderRepository->cancel($order, $reason);

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'old_status' => OrderStatus::PENDING->value,
                    'new_status' => OrderStatus::CANCELLED->value,
                    'note' => 'Khách hàng hủy đơn: ' . $reason,
                ]);

                $items = $this->orderRepository->getOrderItems($order->order_id);

                $this->variantRepository->restoreStockFromOrderItems($items);
            });

            return [
                'status_code' => 200,
                'body' => [
                    'status' => 'success',
                    'message' => 'Đã hủy đơn hàng thành công!',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Order cancel error: ' . $e->getMessage());

            return $this->error('Lỗi khi hủy đơn.', 500);
        }
    }

    private function resolveAddress(int $userId, array $data)
    {
        if (!empty($data['address_id'])) {
            $address = $this->addressRepository->findUserAddress(
                $userId,
                $data['address_id']
            );

            if (!$address) {
                throw new \Exception('Địa chỉ không hợp lệ!');
            }

            return $address;
        }

        return $this->addressRepository->create([
            'user_id' => $userId,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'province' => $data['province'],
            'district' => $data['district'],
            'ward' => $data['ward'],
            'address_line' => $data['address_line'],
            'province_code' => $data['province_code'] ?? null,
            'district_code' => $data['district_code'] ?? null,
            'ward_code' => $data['ward_code'] ?? null,
            'is_default' => false,
        ]);
    }

    private function calculateSubtotalAndValidateStock($cartItems): float
    {
        $subtotal = 0;

        foreach ($cartItems as $item) {
            if ($item->variant->stock < $item->quantity) {
                throw new \Exception(
                    'Sản phẩm ' . $item->variant->product->name . ' không đủ tồn kho!'
                );
            }

            $subtotal += $item->variant->price * $item->quantity;
        }

        return $subtotal;
    }

    private function lockAndValidateStock($cartItems): void
    {
        $variantIds = $cartItems->pluck('variant_id')->toArray();

        $lockedVariants = $this->variantRepository->lockVariants($variantIds);

        foreach ($cartItems as $cartItem) {
            $lockedVariant = $lockedVariants[$cartItem->variant_id] ?? null;

            if (!$lockedVariant || $lockedVariant->stock < $cartItem->quantity) {
                throw new \Exception(
                    'Sản phẩm ' . $cartItem->variant->product->name . ' đã hết hàng khi bạn đặt mua!'
                );
            }
        }
    }

    private function makeFullAddress($address): string
    {
        return implode(', ', array_filter([
            $address->address_line,
            $address->ward,
            $address->district,
            $address->province,
        ]));
    }

    private function generateOrderCode(): string
    {
        return 'ORD' . strtoupper(uniqid()) . rand(10, 99);
    }

    private function dispatchOrderCreatedEvent($order): void
    {
        try {
            event(new \App\Events\OrderCreatedAdmin($order));
        } catch (\Exception $e) {
            Log::error('Realtime event dispatch failed: ' . $e->getMessage());
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
}
