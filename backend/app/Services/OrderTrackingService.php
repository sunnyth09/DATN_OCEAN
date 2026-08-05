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
        $order->loadMissing(['address', 'items.product', 'items.variant']);

        return [
            'order_code' => $order->order_code,
            // Ocean Express tracking number (e.g. OE-1712345678)
            'tracking_number' => $order->tracking_number,
            'tracking_url' => $this->buildTrackingUrl($order),
            // Keep ghn_order_code for backward compat with older orders
            'ghn_order_code' => $order->ghn_order_code,
            'ghn_tracking_url' => $this->buildGhnTrackingUrl($order),
            'fulfillment_status' => $order->fulfillment_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'grand_total' => $order->grand_total,
            'receiver_name' => $this->maskName($this->receiverName($order)),
            'receiver_phone' => $this->maskPhone($this->receiverPhone($order)),
            'timeline' => $this->getTimeline($order),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'color' => $item->color,
                'size' => $item->size,
                'image' => $item->variant?->image_url ?: $item->product?->thumbnail_url ?: $item->product?->main_image ?: null,
            ])->toArray(),
        ];
    }

    public function getTimeline(Order $order): array
    {
        $dbEvents = $this->dbEvents($order);
        $oeEvents = $this->oceanExpressEvents($order, $dbEvents);

        $merged = $dbEvents->concat($oeEvents)
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

    /**
     * Fetch tracking logs from Ocean Express public API.
     * Replaces the old ghnEvents() method.
     * Ocean Express log format: { status, timestamp, note }
     */
    private function oceanExpressEvents(Order $order, Collection $dbEvents): Collection
    {
        // Use tracking_number (Ocean Express) if available; fall back to ghn_order_code for old orders
        $trackingNumber = $order->tracking_number;

        if (! $trackingNumber) {
            // Legacy: try GHN fallback for old orders that haven't been migrated
            return $this->ghnEvents($order, $dbEvents);
        }

        try {
            $data = OceanExpressService::getTracking($trackingNumber);
        } catch (\Throwable $e) {
            Log::warning('Order tracking OceanExpress unavailable', [
                'order_id' => $order->order_id,
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        if (! $data) {
            return collect();
        }

        $logs = $data['logs'] ?? [];
        if (! is_array($logs)) {
            return collect();
        }

        return collect($logs)
            ->filter(fn ($log) => is_array($log))
            ->map(function (array $log) use ($order) {
                // Ocean Express log fields per API spec: status, timestamp, note
                $status = $log['status'] ?? null;
                $timestamp = $log['timestamp'] ?? now()->toIso8601String();
                $note = $log['note'] ?? $status;

                // Map Ocean Express status to local fulfillment_status
                $mappedStatus = $this->mapOceanExpressStatus($status)
                    ?: ($order->fulfillment_status);

                return [
                    'history_id' => null,
                    'old_status' => null,
                    'new_status' => $mappedStatus,
                    'note' => null,
                    'ghn_status' => null,
                    'source' => 'ocean_express',
                    'description' => $note,
                    'location' => null,
                    'happened_at' => $this->formatTime($timestamp),
                    'created_at' => null,
                ];
            })
            ->reject(fn (array $event) => $this->isDuplicateEvent($event, $dbEvents))
            ->values();
    }

    /**
     * Map Ocean Express status string to local fulfillment_status.
     * Per API spec statuses: ready_to_pick, picking, in_hub, delivering, delivered, returned
     */
    private function mapOceanExpressStatus(?string $status): ?string
    {
        return match ($status) {
            'ready_to_pick' => 'confirmed',
            'picking' => 'shipping',
            'in_hub' => 'shipping',
            'delivering' => 'shipping',
            'delivered' => 'delivered',
            'returned' => 'return_requested',
            default => null,
        };
    }

    /**
     * Legacy GHN events — used only for orders that still have ghn_order_code (pre-migration).
     */
    private function ghnEvents(Order $order, Collection $dbEvents): Collection
    {
        if (! $order->ghn_order_code) {
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
        if (! is_array($logs)) {
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

    private function isDuplicateEvent(array $event, Collection $dbEvents): bool
    {
        $eventTime = strtotime($event['happened_at'] ?? '') ?: null;
        $source = $event['source'] ?? 'ocean_express';

        return $dbEvents->contains(function (array $dbEvent) use ($event, $eventTime) {
            // For OE events, deduplicate by new_status + time proximity
            if (($dbEvent['new_status'] ?? null) !== ($event['new_status'] ?? null)) {
                return false;
            }
            if (! $eventTime) {
                return true;
            }
            $dbTime = strtotime($dbEvent['happened_at'] ?? '') ?: null;

            return $dbTime && abs($dbTime - $eventTime) <= 300;
        });
    }

    private function isDuplicateGhnEvent(array $event, Collection $dbEvents): bool
    {
        $eventTime = strtotime($event['happened_at'] ?? '') ?: null;

        return $dbEvents->contains(function (array $dbEvent) use ($event, $eventTime) {
            if (($dbEvent['ghn_status'] ?? null) !== ($event['ghn_status'] ?? null)) {
                return false;
            }

            if (! $eventTime) {
                return true;
            }

            $dbTime = strtotime($dbEvent['happened_at'] ?? '') ?: null;
            if (! $dbTime) {
                return ($dbEvent['new_status'] ?? null) === ($event['new_status'] ?? null);
            }

            return abs($dbTime - $eventTime) <= 300;
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

    /**
     * Build Ocean Express public tracking URL.
     */
    private function buildTrackingUrl(Order $order): ?string
    {
        if (! $order->tracking_number) {
            return null;
        }

        // Trang tra cứu công khai dành cho khách (không phải endpoint API).
        // Dùng config: env() trả null sau `php artisan config:cache`.
        return rtrim((string) config('ocean_express.tracking_url'), '/')
            .'/'.urlencode($order->tracking_number);
    }

    private function buildGhnTrackingUrl(Order $order): ?string
    {
        if (! $order->ghn_order_code) {
            return null;
        }

        return rtrim((string) config('ghn.tracking_url', 'https://donhang.ghn.vn'), '/')
            .'/?order_code='.urlencode($order->ghn_order_code);
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
            return $digits ? substr($digits, 0, 2).'***' : '';
        }

        return substr($digits, 0, 3).'****'.substr($digits, -3);
    }

    private function maskName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) <= 1) {
            return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
        }

        $last = array_pop($parts);

        return str_repeat('* ', count($parts)).$last;
    }
}
