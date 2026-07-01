<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\GHNService;
use App\Services\GhnOrderStatusSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGhnOrderStatus extends Command
{
    protected $signature = 'ghn:sync-status {--order_id=} {--limit=50}';

    protected $description = 'Đồng bộ trạng thái vận đơn GHN về đơn hàng local';

    public function handle(GhnOrderStatusSyncService $statusSyncService): int
    {
        $query = Order::whereNotNull('ghn_order_code')
            ->whereIn('fulfillment_status', ['pending', 'confirmed', 'processing', 'packing', 'shipping', 'delivered']);

        if ($this->option('order_id')) {
            $query->where('order_id', (int) $this->option('order_id'));
        }

        $orders = $query->orderByDesc('order_id')->limit((int) $this->option('limit'))->get();
        $synced = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $detail = GHNService::getOrderDetail($order->ghn_order_code);
                if (empty($detail)) {
                    $this->warn("Order #{$order->order_id}: GHN không trả dữ liệu.");
                    continue;
                }

                $result = $statusSyncService->syncFromDetail($order, $detail, 'ghn_api');
                $synced++;
                $this->info("Order #{$order->order_id}: {$result['ghn_status']} -> {$result['new_status']}" . ($result['changed'] ? ' (changed)' : ''));
            } catch (\Throwable $e) {
                $failed++;
                Log::error('GHN status sync command error', [
                    'order_id' => $order->order_id,
                    'ghn_order_code' => $order->ghn_order_code,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Order #{$order->order_id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Synced: {$synced}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
