<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OceanExpressOrderStatusSyncService;
use App\Services\OceanExpressService;
use Illuminate\Console\Command;

class SyncOceanExpressOrdersCommand extends Command
{
    protected $signature = 'oceanexpress:sync-orders';

    protected $description = 'Chạy fallback kéo trạng thái đơn hàng Ocean Express đang giao (chống sót webhook)';

    public function handle(OceanExpressOrderStatusSyncService $syncService)
    {
        $this->info('Starting OceanExpress order sync fallback...');

        $orders = Order::where('carrier', 'ocean_express')
            ->whereIn('fulfillment_status', ['awaiting_pickup', 'shipping'])
            ->whereNotNull('tracking_number')
            ->get();

        $count = $orders->count();
        $this->info("Found {$count} active orders to sync.");

        $successCount = 0;
        $failCount = 0;

        foreach ($orders as $order) {
            $tracking = OceanExpressService::getTracking($order->tracking_number);

            if (! $tracking || empty($tracking['logs'])) {
                $this->error("Failed to fetch tracking or no logs for order {$order->order_code} ({$order->tracking_number})");
                $failCount++;

                continue;
            }

            // Sync tất cả logs (lịch sử) để đảm bảo không lọt log nào
            // Nhờ idempotency trong SyncService, các log cũ sẽ bị bỏ qua
            foreach ($tracking['logs'] as $log) {
                $payload = [
                    'status' => $log['status'],
                    'timestamp' => $log['timestamp'] ?? null,
                    'note' => $log['note'] ?? null,
                ];

                $syncService->syncFromWebhookPayload($order, $payload);
            }

            $this->info("Synced order {$order->order_code}");
            $successCount++;
        }

        $this->info("Sync completed! Success: {$successCount}, Failed: {$failCount}.");

        return 0;
    }
}
