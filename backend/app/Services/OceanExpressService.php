<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OceanExpressService
{
    /**
     * Base URL đọc từ config (KHÔNG dùng env() trực tiếp — env() trả null khi
     * production đã chạy `php artisan config:cache`).
     */
    private static function url(string $path): string
    {
        return config('ocean_express.api_url').'/'.ltrim($path, '/');
    }

    /**
     * HTTP client dùng chung: có API key + timeout để một request treo không
     * kéo theo cả worker queue/web.
     */
    private static function client(bool $withApiKey = true): PendingRequest
    {
        $client = Http::timeout((int) config('ocean_express.timeout', 5))
            ->acceptJson();

        if ($withApiKey) {
            $client = $client->withHeaders([
                'X-API-Key' => config('ocean_express.api_key'),
            ]);
        }

        return $client;
    }

    /**
     * Get locations from Ocean Express API.
     * Uses query params type & parent_id as per API spec.
     *
     * @param  string|null  $type  'province', 'district', 'ward'
     * @param  string|null  $parentId  Parent location ID (e.g. 'VN-01')
     */
    public static function getLocations(?string $type = null, ?string $parentId = null): array
    {
        // Build a specific cache key so each unique filter combination is cached separately
        $cacheKey = 'ocean_express_locations_'.($type ?? 'all').'_'.($parentId ?? 'root');

        return Cache::remember($cacheKey, 86400, function () use ($type, $parentId) {
            $params = [];
            if ($type) {
                $params['type'] = $type;
            }
            if ($parentId) {
                $params['parent_id'] = $parentId;
            }

            try {
                $response = self::client(false)->get(self::url('locations'), $params);
                if ($response->successful()) {
                    return $response->json('data', []);
                }
                Log::warning('OceanExpress getLocations non-200: '.$response->status().' '.$response->body());
            } catch (\Throwable $e) {
                Log::error('OceanExpress getLocations error: '.$e->getMessage());
            }

            return [];
        });
    }

    /**
     * Calculate shipping rate via Ocean Express.
     * receiver_location_id must be a valid Ocean Express location ID (e.g. 'VN-01-00004').
     *
     * @return array{fee: int}
     */
    public static function calculateRateDetailed(string $receiverLocationId, int $weight): array
    {
        $fallbackFee = (int) config('ocean_express.fallback_fee', 30000);

        try {
            $response = self::client()->post(self::url('rates/calculate'), [
                'receiver_location_id' => $receiverLocationId,
                'weight' => $weight,
            ]);

            if ($response->successful()) {
                $fee = $response->json('data.fee');

                if ($fee !== null && is_numeric($fee)) {
                    return ['fee' => (int) $fee];
                }

                Log::warning('OceanExpress calculateRate: response thiếu data.fee — '.$response->body());
            } else {
                Log::warning('OceanExpress calculateRate failed: '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::error('OceanExpress calculateRate error: '.$e->getMessage());
        }

        return ['fee' => $fallbackFee];
    }

    /**
     * Phiên bản chỉ trả về số tiền — giữ cho các nơi gọi cũ.
     */
    public static function calculateRate(string $receiverLocationId, int $weight): int
    {
        return self::calculateRateDetailed($receiverLocationId, $weight)['fee'];
    }

    /**
     * Create a shipment order in Ocean Express.
     * Request body per API spec:
     *   receiver_name, receiver_phone, receiver_location_id,
     *   receiver_address_detail, weight, cod_amount
     */
    public static function createOrder(array $orderData): ?array
    {
        try {
            $response = self::client()->post(self::url('orders'), $orderData);

            if ($response->successful()) {
                // Returns: { id, tracking_number, status, shipping_fee, estimated_delivery_time }
                return $response->json('data');
            }

            Log::error('OceanExpress createOrder failed: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('OceanExpress createOrder error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Fetch public tracking info for a shipment.
     * This endpoint is public — no API key required.
     *
     * Response data: { tracking_number, status, sender_address, receiver_address, logs[] }
     * Each log: { status, timestamp, note }
     */
    public static function getTracking(string $trackingNumber): ?array
    {
        try {
            $response = self::client(false)->get(self::url('public/tracking/'.urlencode($trackingNumber)));

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('OceanExpress getTracking failed: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('OceanExpress getTracking error: '.$e->getMessage());
        }

        return null;
    }
}
