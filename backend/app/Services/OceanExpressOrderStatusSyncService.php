<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Repositories\AdminOrderRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OceanExpressOrderStatusSyncService
{
    public function __construct(
        protected WalletService $walletService,
        protected AdminOrderRepository $orderRepository
    ) {}

    private const TERMINAL_STATUSES = ['completed', 'cancelled', 'returned', 'refunded'];

    public function mapStatus(string $status): ?string
    {
        return [
            'pending' => OrderStatus::PENDING->value,
            'picking' => OrderStatus::AWAITING_PICKUP->value,
            'shipping' => OrderStatus::SHIPPING->value,
            'delivering' => OrderStatus::SHIPPING->value,
            'delivered' => OrderStatus::DELIVERED->value,
            'completed' => OrderStatus::COMPLETED->value,
            'cancelled' => OrderStatus::CANCELLED->value,
            'returned' => OrderStatus::RETURNED->value,
        ][$status] ?? null;
    }

    public function syncFromWebhookPayload(Order $order, array $payload): array
    {
        $oeStatus = $payload['status'] ?? null;
        $mappedStatus = $oeStatus ? $this->mapStatus($oeStatus) : null;
        $happenedAt = $this->parseHappenedAt($payload);
        $description = $payload['note'] ?? $oeStatus;
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;

        if (! $oeStatus || ! $mappedStatus) {
            return [
                'changed' => false,
                'history_created' => false,
                'old_status' => $order->fulfillment_status,
                'new_status' => $order->fulfillment_status,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $oeStatus, // Kept for history backward compat
                'happened_at' => $happenedAt->toIso8601String(),
                'description' => $description,
                'message' => 'Ocean Express webhook status không được hỗ trợ.',
            ];
        }

        // Cache lock để chống race condition khi webhook gọi đồng thời
        $lockKey = 'oe_webhook_sync_'.$order->order_id.'_'.$oeStatus;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return [
                'changed' => false,
                'history_created' => false,
                'old_status' => $order->fulfillment_status,
                'new_status' => $order->fulfillment_status,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $oeStatus,
                'happened_at' => $happenedAt->toIso8601String(),
                'description' => $description,
                'message' => 'Hệ thống đang xử lý webhook này (lock) — bỏ qua.',
            ];
        }

        try {
            // Idempotency: hãng vận chuyển gửi lại webhook khi không nhận được 2xx.
            // Bỏ qua payload đã xử lý để không tạo dòng lịch sử trùng (và không chạy
            // lại nhánh cancelled → hoàn tiền ví / hoàn tồn kho).
            if ($this->isDuplicate($order, $oeStatus, $payload)) {
                return [
                    'changed' => false,
                    'history_created' => false,
                    'old_status' => $order->fulfillment_status,
                    'new_status' => $order->fulfillment_status,
                    'mapped_status' => $mappedStatus,
                    'ghn_status' => $oeStatus,
                    'happened_at' => $happenedAt->toIso8601String(),
                    'description' => $description,
                    'message' => 'Webhook đã được xử lý trước đó (duplicate) — bỏ qua.',
                ];
            }

            return $this->applyStatus($order, $mappedStatus, $oeStatus, 'ocean_express_webhook', $happenedAt, $description, $latitude, $longitude);
        } finally {
            $lock->release();
        }
    }

    /**
     * Đã có bản ghi lịch sử cùng trạng thái + cùng mốc thời gian từ hãng vận chuyển?
     *
     * Chỉ chống trùng khi payload CÓ `timestamp`. Nếu thiếu timestamp thì
     * parseHappenedAt() trả về now(), không thể dùng làm khoá so sánh — khi đó
     * cho đi tiếp và dựa vào shouldUpdateOrder() để không đổi trạng thái sai.
     */
    private function isDuplicate(Order $order, string $oeStatus, array $payload): bool
    {
        if (empty($payload['timestamp'])) {
            return false;
        }

        return OrderStatusHistory::where('order_id', $order->order_id)
            ->where('ghn_status', $oeStatus)
            ->where('happened_at', $this->parseHappenedAt($payload))
            ->exists();
    }

    private function applyStatus(
        Order $order,
        string $mappedStatus,
        string $oeStatus,
        string $source,
        Carbon $happenedAt,
        ?string $description,
        ?float $latitude,
        ?float $longitude
    ): array {
        return DB::transaction(function () use ($order, $mappedStatus, $oeStatus, $source, $happenedAt, $description, $latitude, $longitude) {
            $oldStatus = $order->fulfillment_status;
            $shouldUpdateOrder = $this->shouldUpdateOrder($oldStatus, $mappedStatus);

            if ($shouldUpdateOrder) {
                $updates = ['fulfillment_status' => $mappedStatus];

                if ($mappedStatus === 'delivered') {
                    $updates['delivered_at'] = $happenedAt;
                }

                if ($mappedStatus === 'completed') {
                    $updates['delivered_at'] = $happenedAt;
                    $updates['completed_at'] = $happenedAt;
                }

                if ($mappedStatus === 'cancelled') {
                    $updates['cancelled_at'] = $happenedAt;
                    $updates['cancel_reason'] = $description ?: 'Canceled by Ocean Express';

                    if (in_array($order->payment_method, ['vnpay', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
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

                    if ($order->items) {
                        $this->orderRepository->restoreStock($order->items);
                    }
                }

                $order->update($updates);
            }

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'old_status' => $oldStatus,
                'new_status' => $shouldUpdateOrder ? $mappedStatus : $oldStatus,
                'note' => 'Cập nhật tự động từ Ocean Express (Webhook)',
                'ghn_status' => $oeStatus, // Ghi log vào cột ghn_status cũ để hiển thị
                'source' => $source,
                'description' => $description,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'happened_at' => $happenedAt,
            ]);

            return [
                'changed' => $shouldUpdateOrder && $oldStatus !== $mappedStatus,
                'history_created' => true,
                'old_status' => $oldStatus,
                'new_status' => $shouldUpdateOrder ? $mappedStatus : $oldStatus,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $oeStatus,
                'happened_at' => $happenedAt->toIso8601String(),
                'description' => $description,
                'message' => $shouldUpdateOrder
                    ? 'Đã đồng bộ trạng thái Ocean Express.'
                    : 'Không cập nhật order vì trạng thái local đang ở trạng thái cuối hoặc không thay đổi trạng thái.',
            ];
        });
    }

    private const STATUS_WEIGHTS = [
        'pending' => 10,
        'confirmed' => 20,
        'processing' => 30,
        'packing' => 40,
        'awaiting_pickup' => 45,
        'shipping' => 50,
        'delivered' => 60,
        'completed' => 70,
    ];

    private function shouldUpdateOrder(?string $currentStatus, string $mappedStatus): bool
    {
        if ($currentStatus === $mappedStatus) {
            return false;
        }

        if (isset(self::STATUS_WEIGHTS[$currentStatus], self::STATUS_WEIGHTS[$mappedStatus])) {
            if (self::STATUS_WEIGHTS[$mappedStatus] < self::STATUS_WEIGHTS[$currentStatus]) {
                return false;
            }
        }

        if (in_array($currentStatus, self::TERMINAL_STATUSES, true) && ! in_array($mappedStatus, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        return true;
    }

    private function parseHappenedAt(array $data): Carbon
    {
        $time = $data['timestamp'] ?? null;

        if (is_numeric($time)) {
            return Carbon::createFromTimestamp((int) $time);
        }

        if (is_string($time) && $time !== '') {
            try {
                return Carbon::parse($time);
            } catch (\Throwable) {
                return now();
            }
        }

        return now();
    }
}
