<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\GhnOrderStatusSyncService;
use App\Services\GHNService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGhnOrderStatus extends Command
{
    protected $signature = 'ghn:sync-status {--order_id=} {--limit=50}';

    protected $description = 'Đồng bộ trạng thái vận đơn GHN về đơn hàng local';

    public function handle(GhnOrderStatusSyncService $statusSyncService): int
    {
        $query = Order::where(function($q) {
                $q->whereNotNull('tracking_number')
                  ->where('tracking_number', '!=', 'SELF-DELIVERY');
            })
            ->orWhereNotNull('ghn_order_code')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('fulfillment_status', ['pending', 'confirmed', 'processing', 'packing', 'shipping', 'delivered']);

        if ($this->option('order_id')) {
            $query->where('order_id', (int) $this->option('order_id'));
        }

        $orders = $query->orderByDesc('order_id')->limit((int) $this->option('limit'))->get();
        $synced = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $trackingCode = $order->tracking_number ?? $order->ghn_order_code;
                
                // Giả định tracking_number là OceanExpress nếu trùng
                if ($order->tracking_number === $trackingCode) {
                    $detail = \App\Services\OceanExpressService::getTracking($trackingCode);
                    if (empty($detail)) {
                        $this->warn("Order #{$order->order_id}: OceanExpress không trả dữ liệu.");
                        continue;
                    }
                    
                    $oeStatus = $detail['status'] ?? null;
                    $oeSyncService = app(\App\Services\OceanExpressOrderStatusSyncService::class);
                    $mappedStatus = $oeStatus ? $oeSyncService->mapStatus($oeStatus) : null;
                    
                    if ($mappedStatus && $mappedStatus !== $order->fulfillment_status) {
                        $oldStatus = $order->fulfillment_status;
                        $order->update(['fulfillment_status' => $mappedStatus]);
                        
                        $rawLogs = $detail['tracking_logs'] ?? $detail['logs'] ?? [];
                        $latestLog = collect($rawLogs)->sortByDesc('created_at')->first() ?? collect($rawLogs)->sortByDesc('timestamp')->first();
                        $logTime = ! empty($latestLog['created_at'])
                            ? $latestLog['created_at']
                            : (! empty($latestLog['timestamp']) ? $latestLog['timestamp'] : now());

                        \App\Models\OrderStatusHistory::create([
                            'order_id' => $order->order_id,
                            'old_status' => $oldStatus,
                            'new_status' => $mappedStatus,
                            'note' => 'Auto-sync từ Ocean Express',
                            'source' => 'system',
                            'description' => $latestLog['note'] ?? ($detail['status_description'] ?? $oeStatus),
                            'happened_at' => $oeSyncService->parseHappenedAt(['timestamp' => $logTime]),
                            'location' => $detail['receiver_address_detail'] ?? $detail['receiver_address'] ?? null,
                            'ghn_status' => $oeStatus,
                        ]);
                        $synced++;
                        $this->info("Order #{$order->order_id} (OE): {$oeStatus} -> {$mappedStatus} (changed)");
                    }
                } else {
                    // Fallback GHN cũ
                    $detail = GHNService::getOrderDetail($order->ghn_order_code);
                    if (empty($detail)) {
                        $this->warn("Order #{$order->order_id}: GHN không trả dữ liệu.");
                        continue;
                    }

                    $result = $statusSyncService->syncFromDetail($order, $detail, 'ghn_api');
                    if ($result['changed']) {
                        $synced++;
                    }
                    $this->info("Order #{$order->order_id} (GHN): {$result['ghn_status']} -> {$result['new_status']}".($result['changed'] ? ' (changed)' : ''));
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Status sync command error', [
                    'order_id' => $order->order_id,
                    'tracking_code' => $order->tracking_number ?? $order->ghn_order_code,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Order #{$order->order_id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Synced: {$synced}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
