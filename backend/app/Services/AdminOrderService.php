<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderShippingMail;
use App\Models\Order;
use App\Notifications\SystemNotification;
use App\Repositories\AdminOrderRepository;
use App\StateMachines\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AdminOrderService
{
    // ALLOWED_TRANSITIONS moved to OrderStateMachine

    private const STATUS_FIELD_MAP = [
        'confirmed' => 'confirmed_at',
        'processing' => 'processing_at',
        'packing' => 'packing_at',
        'shipping' => 'shipped_at',
        'delivered' => 'delivered_at',
        'completed' => 'completed_at',
        'cancelled' => 'cancelled_at',
    ];

    public function __construct(
        protected AdminOrderRepository $orderRepository,
        protected AffiliateService $affiliateService,
        protected LoyaltyService $loyaltyService,
        protected WalletService $walletService,
    ) {}

    /**
     * Lấy danh sách đơn hàng cho Admin
     */
    public function listOrders(Request $request): array
    {
        $filters = [
            'status' => $request->status,
            'payment_status' => $request->payment_status,
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ];

        $orders = $this->orderRepository->getFilteredOrders($filters, $request->per_page ?? 10);

        // Append available_transitions
        $orders->getCollection()->transform(function ($order) {
            $order->available_transitions = OrderStateMachine::getAvailableTransitions($order, 'admin');

            return $order;
        });

        return ['status' => 'success', 'data' => $orders];
    }

    /**
     * Lấy chi tiết đơn hàng
     */
    public function showOrder(int $id): array
    {
        $order = $this->orderRepository->findWithRelations($id);

        if (! $order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        $order->available_transitions = OrderStateMachine::getAvailableTransitions($order, 'admin');

        return ['_status' => 200, 'status' => 'success', 'data' => $order];
    }

    /**
     * Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(int $id, array $data): array
    {
        $order = $this->orderRepository->find($id);
        if (! $order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        $newFulfillmentStatus = $data['fulfillment_status'] ?? null;

        if ($newFulfillmentStatus) {
            // Khóa cập nhật các trạng thái thuộc về đơn vị vận chuyển nếu dùng đối tác thứ 3
            if ($order->tracking_number && $order->tracking_number !== 'SELF-DELIVERY') {
                $carrierStatuses = [
                    OrderStatus::SHIPPING->value,
                    OrderStatus::DELIVERED->value,
                    OrderStatus::RETURNING->value,
                    OrderStatus::RETURNED->value,
                    OrderStatus::WAREHOUSE_RECEIVED->value,
                ];
                if (in_array($newFulfillmentStatus, $carrierStatuses, true)) {
                    return [
                        '_status' => 422,
                        'status' => 'error',
                        'message' => "Đơn hàng đang được xử lý bởi đối tác vận chuyển. Không thể thủ công cập nhật trạng thái giao hàng!",
                    ];
                }
            }

            // Kiểm tra trùng trạng thái
            if ($newFulfillmentStatus === $order->fulfillment_status) {
                return [
                    '_status' => 422,
                    'status' => 'error',
                    'message' => "Đơn hàng đang ở trạng thái '{$order->fulfillment_status}' rồi. Vui lòng chọn trạng thái tiếp theo!",
                ];
            }

            // Kiểm tra luồng trạng thái hợp lệ
            if (! OrderStateMachine::canTransition($order, $newFulfillmentStatus, 'admin')) {
                return [
                    '_status' => 422,
                    'status' => 'error',
                    'message' => "Không thể chuyển từ '{$order->fulfillment_status}' sang '{$newFulfillmentStatus}' bởi Admin. Vui lòng thực hiện theo đúng quy trình!",
                ];
            }
        }

        return $this->processStatusUpdate($order, $newFulfillmentStatus, $data, false);
    }

    /**
     * Cập nhật trạng thái đơn hàng (Bỏ qua StateMachine)
     */
    public function forceStatus(int $id, array $data): array
    {
        $order = $this->orderRepository->find($id);
        if (! $order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        $newFulfillmentStatus = $data['fulfillment_status'] ?? null;

        if ($newFulfillmentStatus && $newFulfillmentStatus === $order->fulfillment_status) {
            return [
                '_status' => 422,
                'status' => 'error',
                'message' => "Đơn hàng đang ở trạng thái '{$order->fulfillment_status}' rồi.",
            ];
        }

        // Bỏ qua validate của StateMachine
        // Mọi logic cập nhật bên trong giống như updateStatus

        // Gọi lại logic chung cập nhật
        return $this->processStatusUpdate($order, $newFulfillmentStatus, $data, true);
    }

    /**
     * Logic dùng chung cho updateStatus và forceStatus (tránh lặp code)
     */
    private function processStatusUpdate(Order $order, ?string $newFulfillmentStatus, array $data, bool $isForce = false): array
    {
        DB::beginTransaction();
        try {
            $oldFulfillmentStatus = $order->fulfillment_status;
            $oldPaymentStatus = $order->payment_status;
            $updates = [];

            if ($newFulfillmentStatus) {
                $updates['fulfillment_status'] = $newFulfillmentStatus;

                if (isset(self::STATUS_FIELD_MAP[$newFulfillmentStatus])) {
                    $updates[self::STATUS_FIELD_MAP[$newFulfillmentStatus]] = now();
                }

                if (in_array($newFulfillmentStatus, [OrderStatus::DELIVERED->value, OrderStatus::COMPLETED->value], true)
                    && $order->payment_method === 'cod'
                    && $order->payment_status === PaymentStatus::UNPAID->value) {
                    $updates['payment_status'] = PaymentStatus::PAID->value;
                }

                if ($newFulfillmentStatus === OrderStatus::REFUNDED->value && $order->payment_status !== PaymentStatus::REFUNDED->value) {
                    $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                }

                if ($newFulfillmentStatus === OrderStatus::CANCELLED->value) {
                    $updates['cancel_reason'] = $data['note'] ?? ($isForce ? 'Ép hủy bởi Admin' : 'Hủy bởi Admin');

                    if (in_array($order->payment_method, ['vnpay', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                    }

                    if ($order->payment_method === 'wallet' && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                        $walletSpent = (float) ($order->wallet_spent ?? $order->grand_total ?? 0);
                        if ($walletSpent > 0) {
                            $this->walletService->refund(
                                $order->user_id,
                                $walletSpent,
                                "Hoàn tiền hủy đơn hàng #{$order->order_code}",
                                $order->order_id,
                                Order::class
                            );
                        }
                    }

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

                    $this->orderRepository->restoreStock($order->items);

                    if ($order->user && $order->user->email) {
                        Mail::to($order->user->email)->queue(new OrderCancelledMail($order, 'admin', $updates['cancel_reason']));
                    }
                }
            }

            if (! empty($updates)) {
                $order->update($updates);

                if (isset($updates['fulfillment_status'])) {
                    $this->orderRepository->createStatusHistory([
                        'order_id' => $order->order_id,
                        'old_status' => $oldFulfillmentStatus,
                        'new_status' => $updates['fulfillment_status'],
                        'note' => $data['note'] ?? ($isForce ? 'Ép chuyển trạng thái bởi Admin' : 'Chuyển trạng thái bởi Admin'),
                    ]);

                    if ($order->user) {
                        $statusLabel = OrderStatus::tryFrom($updates['fulfillment_status'])?->label() ?? $updates['fulfillment_status'];
                        $title = "Cập nhật đơn hàng #{$order->order_code}";
                        $message = "Đơn hàng của bạn đã được cập nhật sang trạng thái: {$statusLabel}.";
                        Notification::sendNow($order->user, new SystemNotification($title, $message, "/profile/orders/{$order->order_id}", 'package'));
                    }
                }

                if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                    $this->orderRepository->createStatusHistory([
                        'order_id' => $order->order_id,
                        'old_status' => $oldPaymentStatus,
                        'new_status' => $updates['payment_status'],
                        'note' => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
                    ]);
                }
            }

            DB::commit();

            if (isset($updates['fulfillment_status'])) {
                $this->affiliateService->updateConversionOnStatusChange($order, $updates['fulfillment_status']);

                if (in_array($updates['fulfillment_status'], [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::COMPLETED->value,
                ], true) && $order->user) {
                    try {
                        $this->loyaltyService->earnFromOrder($order->user, $order->fresh());

                        $isFirstOrder = Order::where('user_id', $order->user_id)
                            ->where('fulfillment_status', OrderStatus::COMPLETED->value)
                            ->count() === 1;

                        if ($isFirstOrder) {
                            $this->loyaltyService->earnFirstOrder($order->user, $order->fresh());
                        }

                        $freshOrder = $order->fresh();
                        if ($freshOrder->is_abandoned_checkout) {
                            $this->loyaltyService->earnAbandonedCart($order->user, $freshOrder->order_id);
                            $order->update(['is_abandoned_checkout' => false]);
                        }
                    } catch (\Exception $e) {
                        Log::error("LoyaltyEarn failed for order #{$order->order_id}: ".$e->getMessage());
                    }
                }
            }

            return ['_status' => 200, 'status' => 'success', 'message' => 'Cập nhật trạng thái thành công!'];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái đơn hàng lỗi: '.$e->getMessage());

            return ['_status' => 500, 'status' => 'error', 'message' => 'Có lỗi xảy ra!'];
        }
    }

    /**
     * Cập nhật trạng thái hàng loạt
     */
    public function bulkUpdateStatus(array $data): array
    {
        $orders = $this->orderRepository->findByIds($data['order_ids']);

        if ($orders->isEmpty()) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng nào để cập nhật!'];
        }

        $newFulfillmentStatus = $data['fulfillment_status'] ?? null;

        // Validate toàn bộ lô
        $invalidOrders = [];
        foreach ($orders as $order) {
            if ($newFulfillmentStatus && $newFulfillmentStatus !== $order->fulfillment_status) {
                if (! OrderStateMachine::canTransition($order, $newFulfillmentStatus, 'admin')) {
                    $invalidOrders[] = "#{$order->order_code} (Chuyển trạng thái không hợp lệ)";
                }
            }
        }

        if (! empty($invalidOrders)) {
            $invalidList = implode(', ', $invalidOrders);

            return [
                '_status' => 422,
                'status' => 'error',
                'message' => "Hủy thao tác do có đơn hàng không hợp lệ: {$invalidList}. Vui lòng bỏ chọn các đơn này và thử lại!",
            ];
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;

            foreach ($orders as $order) {
                $oldFulfillmentStatus = $order->fulfillment_status;
                $oldPaymentStatus = $order->payment_status;
                $updates = [];

                if ($newFulfillmentStatus && $newFulfillmentStatus !== $order->fulfillment_status) {
                    $updates['fulfillment_status'] = $newFulfillmentStatus;

                    if (isset(self::STATUS_FIELD_MAP[$newFulfillmentStatus])) {
                        $updates[self::STATUS_FIELD_MAP[$newFulfillmentStatus]] = now();
                    }

                    if (in_array($newFulfillmentStatus, [OrderStatus::DELIVERED->value, OrderStatus::COMPLETED->value], true)
                        && $order->payment_method === 'cod'
                        && $order->payment_status === PaymentStatus::UNPAID->value) {
                        $updates['payment_status'] = PaymentStatus::PAID->value;
                    }

                    if ($newFulfillmentStatus === OrderStatus::REFUNDED->value && $order->payment_status !== PaymentStatus::REFUNDED->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                    }

                    if ($newFulfillmentStatus === OrderStatus::CANCELLED->value) {
                        $updates['cancel_reason'] = $data['note'] ?? 'Hủy hàng loạt bởi Admin';

                        if (in_array($order->payment_method, ['vnpay', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
                            $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                        }

                        if ($order->payment_method === 'wallet' && $order->payment_status === PaymentStatus::PAID->value) {
                            $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                            $this->walletService->refund(
                                $order->user_id,
                                (float) $order->wallet_spent,
                                "Hoàn tiền hủy hàng loạt đơn hàng #{$order->order_code}",
                                $order->order_id,
                                Order::class
                            );
                        }

                        // Hoàn ví GIẢM GIÁ (deposit/commission discount) — cột độc lập với
                        // wallet_spent; áp cho mọi payment_method nên không gate theo 'wallet'.
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

                        // Hoàn tồn kho (dùng items đã eager-load, tránh N+1)
                        $this->orderRepository->restoreStock($order->items->filter(fn ($i) => $i->variant_id)->all());

                        // Gửi email thông báo hủy đơn
                        if ($order->user && $order->user->email) {
                            Mail::to($order->user->email)->queue(new OrderCancelledMail($order, 'admin', $updates['cancel_reason']));
                        }
                    }
                }

                if (! empty($updates)) {
                    $order->update($updates);
                    $updatedCount++;

                    if (isset($updates['fulfillment_status'])) {
                        $this->orderRepository->createStatusHistory([
                            'order_id' => $order->order_id,
                            'old_status' => $oldFulfillmentStatus,
                            'new_status' => $updates['fulfillment_status'],
                            'note' => $data['note'] ?? 'Chuyển trạng thái hàng loạt bởi Admin',
                        ]);

                        if ($order->user) {
                            $statusLabel = OrderStatus::tryFrom($updates['fulfillment_status'])?->label() ?? $updates['fulfillment_status'];
                            $title = "Cập nhật đơn hàng #{$order->order_code}";
                            $message = "Đơn hàng của bạn đã được cập nhật sang trạng thái: {$statusLabel}.";
                            Notification::sendNow($order->user, new SystemNotification($title, $message, "/profile/orders/{$order->order_id}", 'package'));
                        }
                    }

                    if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                        $this->orderRepository->createStatusHistory([
                            'order_id' => $order->order_id,
                            'old_status' => $oldPaymentStatus,
                            'new_status' => $updates['payment_status'],
                            'note' => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
                        ]);
                    }
                }
            }

            DB::commit();

            // Affiliate sync + Loyalty earn
            if ($newFulfillmentStatus) {
                $isDeliveredOrCompleted = in_array($newFulfillmentStatus, [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::COMPLETED->value,
                ], true);

                foreach ($orders as $order) {
                    $freshOrder = $order->fresh();
                    $this->affiliateService->updateConversionOnStatusChange($freshOrder, $newFulfillmentStatus);

                    // Tích điểm loyalty
                    if ($isDeliveredOrCompleted && $order->user) {
                        try {
                            $this->loyaltyService->earnFromOrder($order->user, $freshOrder);

                            if ($freshOrder->is_abandoned_checkout) {
                                $this->loyaltyService->earnAbandonedCart($order->user, $freshOrder->order_id);
                                $order->update(['is_abandoned_checkout' => false]);
                            }
                        } catch (\Exception $e) {
                            Log::error("LoyaltyEarn bulk failed for order #{$order->order_id}: ".$e->getMessage());
                        }
                    }
                }
            }

            if ($updatedCount === 0) {
                return [
                    '_status' => 422,
                    'status' => 'error',
                    'message' => 'Tất cả đơn hàng đã ở trạng thái được chọn rồi. Không có gì thay đổi!',
                ];
            }

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Cập nhật trạng thái hàng loạt thành công cho '.$updatedCount.' đơn hàng!',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái hàng loạt lỗi: '.$e->getMessage());

            return ['_status' => 500, 'status' => 'error', 'message' => 'Có lỗi hệ thống xảy ra!'];
        }
    }

    /**
     * Đồng bộ đơn hàng lên Ocean Express
     */
    public function syncGHN(int $id): array
    {
        $lock = Cache::lock("oe_sync_order_{$id}", 30);

        if (! $lock->get()) {
            return ['_status' => 409, 'status' => 'error', 'message' => 'Đơn hàng đang được đồng bộ. Vui lòng thử lại sau!'];
        }

        try {
            $order = Order::with(['items.variant.product', 'address'])->where('order_id', $id)->first();
            if (! $order) {
                return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
            }

            if ($order->tracking_number) {
                return ['_status' => 409, 'status' => 'error', 'message' => 'Đơn hàng đã được đồng bộ vận chuyển (Tracking: '.$order->tracking_number.')!'];
            }

            // receiver_location_id = ward_code (Ocean Express location ID, e.g. 'VN-01-00004')
            $receiverLocationId = $order->ward_code;

            if (empty($receiverLocationId)) {
                return ['_status' => 422, 'status' => 'error', 'message' => 'Đơn hàng thiếu thông tin địa chỉ (ward_code). Không thể tạo vận đơn!'];
            }

            // Trọng lượng dùng CHUNG công thức với ShippingService để phí báo cho
            // khách ở checkout và phí vận đơn thực tế không lệch nhau.
            $totalWeight = app(ShippingService::class)->calculateWeight($order->items);

            // Ocean Express API spec: POST /api/v1/orders
            $orderData = [
                'receiver_name' => $order->recipient_name,
                'receiver_phone' => $order->recipient_phone,
                'receiver_location_id' => $receiverLocationId,
                'receiver_address_detail' => $order->shipping_address,
                'weight' => (int) $totalWeight,
                // cod_amount: số tiền thu hộ — 0 nếu đã thanh toán trước
                'cod_amount' => $order->payment_status === 'paid'
                    ? 0
                    : (int) $order->grand_total,
            ];

            $result = OceanExpressService::createOrder($orderData);

            if (isset($result['tracking_number'])) {
                $oldStatus = $order->fulfillment_status;
                $order->tracking_number = $result['tracking_number'];

                if (! $order->tracking_token) {
                    $order->tracking_token = hash('sha256', $order->order_code.Str::random(40).microtime(true));
                }

                // Tự động chuyển sang trạng thái shipping
                $order->fulfillment_status = OrderStatus::SHIPPING->value;
                $order->shipped_at = now();

                $order->save();

                $this->sendOrderShippingMail($order->fresh(['address']));

                $this->orderRepository->createStatusHistory([
                    'order_id' => $order->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => $order->fulfillment_status,
                    'note' => 'Đã đồng bộ đơn hàng sang Ocean Express',
                    'source' => 'system',
                    'description' => 'Vận đơn: '.$result['tracking_number'].' — Đơn hàng chuyển sang trạng thái Giao hàng',
                    'happened_at' => now(),
                ]);
            } else {
                return ['_status' => 400, 'status' => 'error', 'message' => 'Lỗi tạo đơn vận chuyển: Ocean Express không trả về tracking_number'];
            }

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Đã tạo đơn hàng trên Ocean Express thành công!',
                'data' => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Lỗi syncOceanExpress: '.$e->getMessage()."\n".$e->getTraceAsString());

            return ['_status' => 400, 'status' => 'error', 'message' => $e->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    public function selfDelivery(int $id): array
    {
        $order = $this->orderRepository->find($id);

        if (! $order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        if ($order->tracking_number) {
            return ['_status' => 409, 'status' => 'error', 'message' => 'Đơn hàng đã được đồng bộ vận chuyển. Không thể tự giao!'];
        }

        if (! OrderStateMachine::canTransition($order, OrderStatus::SHIPPING->value, 'admin')) {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Đơn hàng không ở trạng thái hợp lệ để giao hàng!'];
        }

        try {
            DB::beginTransaction();

            $oldStatus = $order->fulfillment_status;
            
            $order->tracking_number = 'SELF-DELIVERY';
            $order->fulfillment_status = OrderStatus::SHIPPING->value;
            $order->shipped_at = now();
            
            $order->save();

            $this->orderRepository->createStatusHistory([
                'order_id' => $order->order_id,
                'old_status' => $oldStatus,
                'new_status' => $order->fulfillment_status,
                'note' => 'Shop tự đi giao hàng cho khách',
                'source' => 'admin',
            ]);

            DB::commit();

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Đã xác nhận tự đi giao hàng thành công!'
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Lỗi selfDelivery: '.$e->getMessage()."\n".$e->getTraceAsString());
            return ['_status' => 500, 'status' => 'error', 'message' => 'Có lỗi xảy ra khi cập nhật đơn hàng.'];
        }
    }

    private function sendOrderShippingMail(Order $order): void
    {
        if (! $order->email) {
            return;
        }

        try {
            Mail::to($order->email)->queue(new OrderShippingMail($order));
        } catch (\Throwable $e) {
            Log::warning('Không thể gửi email tracking GHN', [
                'order_id' => $order->order_id,
                'email' => $order->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
