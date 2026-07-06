<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrderTrackingService
{
    public function __construct(
        protected GhnOrderStatusSyncService $statusSyncService,
    ) {}

    public function buildPayload(Order $order): array
    {
        $order->loadMissing(['address']);

        return [
            'order_code' => $order->order_code,
            'ghn_order_code' => $order->ghn_order_code,
            'ghn_tracking_url' => $this->buildGhnTrackingUrl($order),
            'fulfillment_status' => $order->fulfillment_status,
            'receiver_name' => $this->maskName($this->receiverName($order)),
            'receiver_phone' => $this->maskPhone($this->receiverPhone($order)),
            'timeline' => $this->getTimeline($order),
        ];
    }

    public function getTimeline(Order $order): array
    {
        $dbEvents = $this->dbEvents($order);
        $ghnEvents = $this->ghnEvents($order, $dbEvents);

        $merged = $dbEvents->concat($ghnEvents)
            ->sortBy(fn (array $event) => strtotime($event['happened_at'] ?? $event['created_at'] ?? $event['time'] ?? 'now'))
            ->values()
            ->map(function (array $event) {
                $event['is_current'] = false;
                return $event;
            });

        if ($merged->isNotEmpty()) {
            $lastIndex = $merged->count() - 1;
            $last = $merged->get($lastIndex);
            $last['is_current'] = true;
            $merged->put($lastIndex, $last);
        }

        return $merged->all();
    }

    private function dbEvents(Order $order): Collection
    {
        return OrderStatusHistory::where('order_id', $order->order_id)
            ->orderBy('happened_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrderStatusHistory $history) => [
                'history_id' => $history->history_id,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'note' => $history->note,
                'ghn_status' => $history->ghn_status,
                'source' => $history->source ?: 'system',
                'description' => $history->description,
                'location' => $history->location,
                'happened_at' => optional($history->happened_at ?: $history->created_at)->toIso8601String(),
                'created_at' => optional($history->created_at)->toIso8601String(),
            ]);
    }

    private function ghnEvents(Order $order, Collection $dbEvents): Collection
    {
        if (!$order->ghn_order_code) {
            return collect();
        }

        try {
            $detail = GHNService::getOrderDetail($order->ghn_order_code);
        } catch (\Throwable $e) {
            Log::warning('Order tracking GHN detail unavailable', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }

        $logs = $detail['log'] ?? $detail['logs'] ?? [];
        if (!is_array($logs)) {
            return collect();
        }

        return collect($logs)
            ->filter(fn ($log) => is_array($log))
            ->map(function (array $log) use ($order) {
                $ghnStatus = $log['status'] ?? $log['Status'] ?? null;
                $mappedStatus = $ghnStatus ? $this->statusSyncService->mapGhnStatus($ghnStatus) : null;
                $time = $log['updated_date'] ?? $log['UpdatedDate'] ?? $log['time'] ?? $log['Time'] ?? now()->toIso8601String();

                return [
                    'history_id' => null,
                    'old_status' => null,
                    'new_status' => $mappedStatus ?: $order->fulfillment_status,
                    'note' => null,
                    'ghn_status' => $ghnStatus,
                    'source' => 'ghn_api',
                    'description' => $log['note'] ?? $log['description'] ?? $log['status_name'] ?? $log['StatusName'] ?? $ghnStatus,
                    'location' => $log['warehouse_name'] ?? $log['Warehouse'] ?? $log['CurrentWarehouseName'] ?? null,
                    'happened_at' => $this->formatTime($time),
                    'created_at' => null,
                ];
            })
            ->reject(fn (array $event) => $this->isDuplicateGhnEvent($event, $dbEvents))
            ->values();
    }

    private function isDuplicateGhnEvent(array $event, Collection $dbEvents): bool
    {
        $eventTime = strtotime($event['happened_at'] ?? '') ?: null;

        return $dbEvents->contains(function (array $dbEvent) use ($event, $eventTime) {
            if (($dbEvent['ghn_status'] ?? null) !== ($event['ghn_status'] ?? null)) {
                return false;
            }

            if (!$eventTime) {
                return true;
            }

            $dbTime = strtotime($dbEvent['happened_at'] ?? '') ?: null;
            return $dbTime && abs($dbTime - $eventTime) <= 60;
        });
    }

    private function formatTime(mixed $value): string
    {
        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value)->toIso8601String();
            }

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value)->toIso8601String();
            }
        } catch (\Throwable) {
            // fallback below
        }

        return now()->toIso8601String();
    }

    private function buildGhnTrackingUrl(Order $order): ?string
    {
        if (!$order->ghn_order_code) {
            return null;
        }

        return rtrim((string) config('ghn.tracking_url', 'https://donhang.ghn.vn'), '/')
            . '/?order_code=' . urlencode($order->ghn_order_code);
    }

    private function receiverName(Order $order): string
    {
        return $order->recipient_name
            ?: ($order->address?->recipient_name ?? $order->address?->name ?? 'Khách hàng');
    }

    private function receiverPhone(Order $order): string
    {
        return $order->recipient_phone ?: ($order->address?->phone ?? '');
    }

    private function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return $digits ? substr($digits, 0, 2) . '***' : '';
        }

        return substr($digits, 0, 3) . '****' . substr($digits, -3);
    }

    private function maskName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) <= 1) {
            return mb_substr($name, 0, 1) . str_repeat('*', max(mb_strlen($name) - 1, 1));
        }

        $last = array_pop($parts);
        return str_repeat('* ', count($parts)) . $last;
    }
}
