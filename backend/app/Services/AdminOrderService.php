<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Repositories\AdminOrderRepository;
use App\Models\Order;
use App\Services\LoyaltyService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderService
{
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'packing', 'cancelled'],
        'processing' => ['packing', 'shipping', 'cancelled'],
        'packing' => ['shipping', 'cancelled'],
        'shipping' => ['delivered'],
        'delivered' => ['completed', 'return_requested'],
        'completed' => ['return_requested'],
        'cancelled' => [],
        'return_requested' => [],
        'return_approved' => [],
        'return_rejected' => [],
        'returned' => [],
        'refunded' => [],
    ];

    private const STATUS_FIELD_MAP = [
        'confirmed' => 'confirmed_at',
        'shipping'  => 'shipped_at',
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
            'status'         => $request->status,
            'payment_status' => $request->payment_status,
            'search'         => $request->search,
            'date_from'      => $request->date_from,
            'date_to'        => $request->date_to,
        ];

        $orders = $this->orderRepository->getFilteredOrders($filters, $request->per_page ?? 10);

        return ['status' => 'success', 'data' => $orders];
    }

    /**
     * Lấy chi tiết đơn hàng
     */
    public function showOrder(int $id): array
    {
        $order = $this->orderRepository->findWithRelations($id);

        if (!$order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        return ['_status' => 200, 'status' => 'success', 'data' => $order];
    }

    /**
     * Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(int $id, array $data): array
    {
        $order = $this->orderRepository->find($id);
        if (!$order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        $newFulfillmentStatus = $data['fulfillment_status'] ?? null;

        if ($newFulfillmentStatus) {
            // Kiểm tra trùng trạng thái
            if ($newFulfillmentStatus === $order->fulfillment_status) {
                return [
                    '_status' => 422,
                    'status'  => 'error',
                    'message' => "Đơn hàng đang ở trạng thái '{$order->fulfillment_status}' rồi. Vui lòng chọn trạng thái tiếp theo!",
                ];
            }

            // Kiểm tra luồng trạng thái hợp lệ
            $allowed = self::ALLOWED_TRANSITIONS[$order->fulfillment_status] ?? [];
            if (!in_array($newFulfillmentStatus, $allowed)) {
                return [
                    '_status' => 422,
                    'status'  => 'error',
                    'message' => "Không thể chuyển từ '{$order->fulfillment_status}' sang '{$newFulfillmentStatus}'. Vui lòng thực hiện theo đúng quy trình!",
                ];
            }
        }

        DB::beginTransaction();
        try {
            $oldFulfillmentStatus = $order->fulfillment_status;
            $oldPaymentStatus     = $order->payment_status;
            $updates = [];

            if ($newFulfillmentStatus) {
                $updates['fulfillment_status'] = $newFulfillmentStatus;

                // Tự động set thời gian
                if (isset(self::STATUS_FIELD_MAP[$newFulfillmentStatus])) {
                    $updates[self::STATUS_FIELD_MAP[$newFulfillmentStatus]] = now();
                }

                // Auto payment status updates
                if (in_array($newFulfillmentStatus, [OrderStatus::DELIVERED->value, OrderStatus::COMPLETED->value], true)
                    && $order->payment_method === 'cod'
                    && $order->payment_status === PaymentStatus::UNPAID->value) {
                    $updates['payment_status'] = PaymentStatus::PAID->value;
                }

                if ($newFulfillmentStatus === OrderStatus::CANCELLED->value) {
                    $updates['cancel_reason'] = $data['note'] ?? 'Hủy bởi Admin';

                    if (in_array($order->payment_method, ['vnpay', 'momo', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                    }

                    if ($order->payment_method === 'wallet' && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                        $this->walletService->refund(
                            $order->user_id,
                            (float) $order->wallet_spent,
                            "Hoàn tiền hủy đơn hàng #{$order->order_code}",
                            $order->order_id,
                            Order::class
                        );
                    }

                    // Hoàn tồn kho
                    $this->orderRepository->restoreStock($order->items);
                }
            }

            if (!empty($updates)) {
                $order->update($updates);

                if (isset($updates['fulfillment_status'])) {
                    $this->orderRepository->createStatusHistory([
                        'order_id'   => $order->order_id,
                        'old_status' => $oldFulfillmentStatus,
                        'new_status' => $updates['fulfillment_status'],
                        'note'       => $data['note'] ?? 'Chuyển trạng thái bởi Admin',
                    ]);
                }

                if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                    $this->orderRepository->createStatusHistory([
                        'order_id'   => $order->order_id,
                        'old_status' => $oldPaymentStatus,
                        'new_status' => $updates['payment_status'],
                        'note'       => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
                    ]);
                }
            }

            DB::commit();

            // Đồng bộ affiliate
            if (isset($updates['fulfillment_status'])) {
                $this->affiliateService->updateConversionOnStatusChange($order, $updates['fulfillment_status']);

                // Tích điểm loyalty khi đơn DELIVERED hoặc COMPLETED
                if (in_array($updates['fulfillment_status'], [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::COMPLETED->value,
                ], true) && $order->user) {
                    try {
                        $this->loyaltyService->earnFromOrder($order->user, $order->fresh());

                        // Bonus đơn đầu tiên
                        $isFirstOrder = Order::where('user_id', $order->user_id)
                            ->where('fulfillment_status', OrderStatus::COMPLETED->value)
                            ->count() === 1;

                        if ($isFirstOrder) {
                            $this->loyaltyService->earnFirstOrder($order->user, $order->fresh());
                        }

                        // Thưởng điểm giỏ hàng bỏ quên nếu có
                        $freshOrder = $order->fresh();
                        if ($freshOrder->is_abandoned_checkout) {
                            $this->loyaltyService->earnAbandonedCart($order->user, $freshOrder->order_id);
                            $order->update(['is_abandoned_checkout' => false]);
                        }
                    } catch (\Exception $e) {
                        Log::error("LoyaltyEarn failed for order #{$order->order_id}: " . $e->getMessage());
                    }
                }
            }

            return ['_status' => 200, 'status' => 'success', 'message' => 'Cập nhật trạng thái thành công!'];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái đơn hàng lỗi: ' . $e->getMessage());
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
                $allowed = self::ALLOWED_TRANSITIONS[$order->fulfillment_status] ?? [];
                if (!in_array($newFulfillmentStatus, $allowed)) {
                    $invalidOrders[] = "#{$order->order_code} (Chuyển Giao hàng không hợp lệ)";
                }
            }
        }

        if (!empty($invalidOrders)) {
            $invalidList = implode(', ', $invalidOrders);
            return [
                '_status' => 422,
                'status'  => 'error',
                'message' => "Hủy thao tác do có đơn hàng không hợp lệ: {$invalidList}. Vui lòng bỏ chọn các đơn này và thử lại!",
            ];
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;

            foreach ($orders as $order) {
                $oldFulfillmentStatus = $order->fulfillment_status;
                $oldPaymentStatus     = $order->payment_status;
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

                    if ($newFulfillmentStatus === OrderStatus::CANCELLED->value) {
                        $updates['cancel_reason'] = $data['note'] ?? 'Hủy hàng loạt bởi Admin';

                        if (in_array($order->payment_method, ['vnpay', 'momo', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
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

                        // Hoàn tồn kho
                        $items = DB::table('order_items')->where('order_id', $order->order_id)->get();
                        $this->orderRepository->restoreStock($items->filter(fn($i) => $i->variant_id)->all());
                    }
                }

                if (!empty($updates)) {
                    $order->update($updates);
                    $updatedCount++;

                    if (isset($updates['fulfillment_status'])) {
                        $this->orderRepository->createStatusHistory([
                            'order_id'   => $order->order_id,
                            'old_status' => $oldFulfillmentStatus,
                            'new_status' => $updates['fulfillment_status'],
                            'note'       => $data['note'] ?? 'Chuyển trạng thái hàng loạt bởi Admin',
                        ]);
                    }

                    if (isset($updates['payment_status']) && $updates['payment_status'] !== $oldPaymentStatus) {
                        $this->orderRepository->createStatusHistory([
                            'order_id'   => $order->order_id,
                            'old_status' => $oldPaymentStatus,
                            'new_status' => $updates['payment_status'],
                            'note'       => '[Thanh toán] Tự động cập nhật theo trạng thái đơn hàng',
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
                    $this->affiliateService->updateConversionOnStatusChange($order->fresh(), $newFulfillmentStatus);

                    // Tích điểm loyalty
                    if ($isDeliveredOrCompleted && $order->user) {
                        try {
                            $this->loyaltyService->earnFromOrder($order->user, $order->fresh());

                            $freshOrder = $order->fresh();
                            if ($freshOrder->is_abandoned_checkout) {
                                $this->loyaltyService->earnAbandonedCart($order->user, $freshOrder->order_id);
                                $order->update(['is_abandoned_checkout' => false]);
                            }
                        } catch (\Exception $e) {
                            Log::error("LoyaltyEarn bulk failed for order #{$order->order_id}: " . $e->getMessage());
                        }
                    }
                }
            }

            if ($updatedCount === 0) {
                return [
                    '_status' => 422,
                    'status'  => 'error',
                    'message' => 'Tất cả đơn hàng đã ở trạng thái được chọn rồi. Không có gì thay đổi!',
                ];
            }

            return [
                '_status' => 200,
                'status'  => 'success',
                'message' => 'Cập nhật trạng thái hàng loạt thành công cho ' . $updatedCount . ' đơn hàng!',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cập nhật trạng thái hàng loạt lỗi: ' . $e->getMessage());
            return ['_status' => 500, 'status' => 'error', 'message' => 'Có lỗi hệ thống xảy ra!'];
        }
    }

    /**
     * Đồng bộ đơn hàng lên GHN
     */
    public function syncGHN(int $id): array
    {
        $order = Order::with(['items', 'address'])->where('order_id', $id)->first();
        if (!$order) {
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'];
        }

        try {
            $result = \App\Services\GHNService::createOrder($order);
            return [
                '_status' => 200,
                'status'  => 'success',
                'message' => 'Đã tạo đơn hàng trên GHN thành công!',
                'data'    => $result,
            ];
        } catch (\Exception $e) {
            return ['_status' => 500, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
