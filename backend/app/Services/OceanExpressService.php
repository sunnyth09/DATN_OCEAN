<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OceanExpressService
{
    /**
     * Base URL Ä‘á»c tá»« config (KHÃ”NG dÃ¹ng env() trá»±c tiáº¿p â€” env() tráº£ null khi
     * production Ä‘Ã£ cháº¡y `php artisan config:cache`).
     */
    private static function url(string $path): string
    {
        return config('ocean_express.api_url').'/'.ltrim($path, '/');
    }

    /**
     * HTTP client dÃ¹ng chung: cÃ³ API key + timeout Ä‘á»ƒ má»™t request treo khÃ´ng
     * kÃ©o theo cáº£ worker queue/web.
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

                Log::warning('OceanExpress calculateRate: response thiáº¿u data.fee â€” '.$response->body());
            } else {
                Log::warning('OceanExpress calculateRate failed: '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::error('OceanExpress calculateRate error: '.$e->getMessage());
        }

        return ['fee' => $fallbackFee];
    }

    /**
     * PhiÃªn báº£n chá»‰ tráº£ vá» sá»‘ tiá»n â€” giá»¯ cho cÃ¡c nÆ¡i gá»i cÅ©.
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
     * This endpoint is public â€” no API key required.
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

    /**
     * Get Print Label URL for Ocean Express order.
     */
    public static function printLabel(string $trackingNumber): array
    {
        try {
            $response = self::client(false)->get(self::url('public/orders/'.urlencode($trackingNumber).'/print-label'));
            if ($response->successful()) {
                $data = $response->json('data', []);
                $labelUrl = $data['label_url'] ?? $data['pdf_url'] ?? self::url('public/orders/'.urlencode($trackingNumber).'/label');
                return [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Láº¥y link in váº­n Ä‘Æ¡n thÃ nh cÃ´ng',
                    'data' => [
                        'token' => 'oe_' . md5($trackingNumber),
                        'print_url' => $labelUrl,
                        'label_url' => $labelUrl,
                        'pdf_url' => $labelUrl,
                        'tracking_number' => $trackingNumber,
                    ]
                ];
            }
            // Fallback direct URL if print-label returned non-200
            $directUrl = self::url('public/orders/'.urlencode($trackingNumber).'/label');
            return [
                'code' => 200,
                'status' => 'success',
                'message' => 'Láº¥y link in váº­n Ä‘Æ¡n thÃ nh cÃ´ng',
                'data' => [
                    'token' => 'oe_' . md5($trackingNumber),
                    'print_url' => $directUrl,
                    'label_url' => $directUrl,
                    'pdf_url' => $directUrl,
                    'tracking_number' => $trackingNumber,
                ]
            ];
        } catch (\Throwable $e) {
            Log::error('OceanExpress printLabel error: ' . $e->getMessage());
            $directUrl = self::url('public/orders/'.urlencode($trackingNumber).'/label');
            return [
                'code' => 200,
                'status' => 'success',
                'message' => 'Láº¥y link in váº­n Ä‘Æ¡n thÃ nh cÃ´ng',
                'data' => [
                    'token' => 'oe_' . md5($trackingNumber),
                    'print_url' => $directUrl,
                    'label_url' => $directUrl,
                    'pdf_url' => $directUrl,
                    'tracking_number' => $trackingNumber,
                ]
            ];
        }
    }

    /**
     * Cancel a shipment order in Ocean Express.
     */
    public static function cancelOrder(string $trackingNumber, string $reason = ''): array
    {
        try {
            $response = self::client()->put(self::url('orders/'.urlencode($trackingNumber).'/status'), [
                'status' => 'cancelled',
                'note' => $reason,
                'failure_reason' => $reason,
            ]);

            if ($response->successful()) {
                return [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Há»§y váº­n Ä‘Æ¡n Ocean Express thÃ nh cÃ´ng',
                    'data' => $response->json('data', []),
                ];
            }

            Log::warning('OceanExpress cancelOrder failed: '.$response->body());
            return [
                'code' => $response->status(),
                'status' => 'error',
                'message' => $response->json('message') ?? 'KhÃ´ng thá»ƒ há»§y Ä‘Æ¡n Ocean Express',
            ];
        } catch (\Throwable $e) {
            Log::error('OceanExpress cancelOrder error: '.$e->getMessage());
            return [
                'code' => 500,
                'status' => 'error',
                'message' => 'Lá»—i káº¿t ná»‘i khi há»§y Ä‘Æ¡n Ocean Express',
            ];
        }
    }
}