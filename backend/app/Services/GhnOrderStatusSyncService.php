<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Repositories\AdminOrderRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GhnOrderStatusSyncService
{
    public function __construct(
        protected WalletService $walletService,
        protected AdminOrderRepository $orderRepository
    ) {}

    private const TERMINAL_STATUSES = ['completed', 'cancelled', 'returned', 'refunded'];

    public function mapGhnStatus(string $status): ?string
    {
        return [
            'ready_to_pick' => 'awaiting_pickup',
            'exception' => 'pending',
            'picking' => 'shipping',
            'money_collect_picking' => 'shipping',
            'picked' => 'shipping',
            'storing' => 'shipping',
            'transporting' => 'shipping',
            'sorting' => 'shipping',
            'delivering' => 'shipping',
            'money_collect_delivering' => 'shipping',
            'delivery_fail' => 'shipping',
            'delivered' => 'delivered',
            'cancel' => 'cancelled',
            'damage' => 'cancelled',
            'lost' => 'cancelled',
            'waiting_to_return' => 'return_requested',
            'return' => 'return_requested',
            'return_transporting' => 'return_requested',
            'return_sorting' => 'return_requested',
            'returning' => 'return_requested',
            'return_fail' => 'return_requested',
            'returned' => 'return_requested',
        ][$status] ?? null;
    }

    public function syncFromDetail(Order $order, array $ghnDetail, string $source = 'ghn_manual_sync'): array
    {
        $ghnStatus = $this->extractStatus($ghnDetail);
        $mappedStatus = $ghnStatus ? $this->mapGhnStatus($ghnStatus) : null;
        $happenedAt = $this->parseHappenedAt($ghnDetail);
        $location = $this->extractLocation($ghnDetail);
        $description = $this->extractDescription($ghnDetail, $ghnStatus);

        if (! $ghnStatus || ! $mappedStatus) {
            return [
                'changed' => false,
                'history_created' => false,
                'old_status' => $order->fulfillment_status,
                'new_status' => $order->fulfillment_status,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $ghnStatus,
                'happened_at' => $happenedAt->toIso8601String(),
                'location' => $location,
                'description' => $description,
                'message' => 'GHN chưa trả trạng thái hoặc trạng thái chưa được hỗ trợ.',
            ];
        }

        return $this->applyStatus($order, $mappedStatus, $ghnStatus, $source, $happenedAt, $description, $location);
    }

    public function syncFromWebhookPayload(Order $order, array $payload): array
    {
        $ghnStatus = $payload['Status'] ?? $payload['status'] ?? null;
        $mappedStatus = $ghnStatus ? $this->mapGhnStatus($ghnStatus) : null;
        $happenedAt = $this->parseHappenedAt($payload);
        $location = $this->extractLocation($payload);
        $description = $this->extractDescription($payload, $ghnStatus);

        if (! $ghnStatus || ! $mappedStatus) {
            return [
                'changed' => false,
                'history_created' => false,
                'old_status' => $order->fulfillment_status,
                'new_status' => $order->fulfillment_status,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $ghnStatus,
                'happened_at' => $happenedAt->toIso8601String(),
                'location' => $location,
                'description' => $description,
                'message' => 'GHN webhook status không được hỗ trợ.',
            ];
        }

        return $this->applyStatus($order, $mappedStatus, $ghnStatus, 'ghn_webhook', $happenedAt, $description, $location);
    }

    private function applyStatus(
        Order $order,
        string $mappedStatus,
        string $ghnStatus,
        string $source,
        Carbon $happenedAt,
        ?string $description,
        ?string $location
    ): array {
        return DB::transaction(function () use ($order, $mappedStatus, $ghnStatus, $source, $happenedAt, $description, $location) {
            $oldStatus = $order->fulfillment_status;
            $shouldUpdateOrder = $this->shouldUpdateOrder($oldStatus, $mappedStatus);
            $lastHistory = OrderStatusHistory::where('order_id', $order->order_id)
                ->whereNotNull('ghn_status')
                ->orderByDesc('happened_at')
                ->orderByDesc('history_id')
                ->first();

            $historyExists = $lastHistory && $lastHistory->ghn_status === $ghnStatus;

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
                    $updates['cancel_reason'] = $description ?: 'Canceled by GHN';

                    if (in_array($order->payment_method, ['vnpay', 'bank_transfer'], true) && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                    }

                    if ($order->payment_method === 'wallet' && $order->payment_status === PaymentStatus::PAID->value) {
                        $updates['payment_status'] = PaymentStatus::REFUNDED->value;
                        $this->walletService->refund(
                            $order->user_id,
                            (float) $order->wallet_spent,
                            "Hoàn tiền hủy đơn hàng (GHN) #{$order->order_code}",
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

                    if ($order->user && $order->user->email) {
                        Mail::to($order->user->email)->queue(new OrderCancelledMail($order, 'system', $updates['cancel_reason']));
                    }
                }

                $order->update($updates);
            }

            $historyCreated = false;
            if (! $historyExists) {
                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => $shouldUpdateOrder ? $mappedStatus : $oldStatus,
                    'note' => $source === 'ghn_webhook'
                        ? 'Cập nhật tự động từ GHN (Webhook)'
                        : 'Đồng bộ trạng thái từ GHN',
                    'ghn_status' => $ghnStatus,
                    'source' => $source,
                    'description' => $description,
                    'location' => $location,
                    'happened_at' => $happenedAt,
                ]);
                $historyCreated = true;
            }

            return [
                'changed' => $shouldUpdateOrder && $oldStatus !== $mappedStatus,
                'history_created' => $historyCreated,
                'old_status' => $oldStatus,
                'new_status' => $shouldUpdateOrder ? $mappedStatus : $oldStatus,
                'mapped_status' => $mappedStatus,
                'ghn_status' => $ghnStatus,
                'happened_at' => $happenedAt->toIso8601String(),
                'location' => $location,
                'description' => $description,
                'message' => $shouldUpdateOrder
                    ? 'Đã đồng bộ trạng thái GHN.'
                    : 'Không cập nhật order vì trạng thái local đang ở trạng thái cuối hoặc GHN không thay đổi trạng thái.',
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

        // Không cho phép đi lùi nếu cả hai trạng thái đều nằm trong luồng xuôi
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

    private function extractStatus(array $data): ?string
    {
        return $data['status'] ?? $data['Status'] ?? null;
    }

    private function extractLocation(array $data): ?string
    {
        return $data['Warehouse']
            ?? $data['CurrentWarehouseName']
            ?? $data['current_warehouse_name']
            ?? $data['warehouse_name']
            ?? null;
    }

    private function extractDescription(array $data, ?string $status): ?string
    {
        return $data['Description']
            ?? $data['description']
            ?? $data['status_name']
            ?? $data['StatusName']
            ?? $status;
    }

    private function parseHappenedAt(array $data): Carbon
    {
        $time = $data['Time']
            ?? $data['time']
            ?? $data['UpdatedDate']
            ?? $data['updated_date']
            ?? $data['updated_at']
            ?? null;

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
