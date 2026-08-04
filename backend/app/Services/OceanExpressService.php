<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OceanExpressService
{
    /**
     * Get locations from Ocean Express API.
     * Uses query params type & parent_id as per API spec.
     *
     * @param  string|null  $type      'province', 'district', 'ward'
     * @param  string|null  $parentId  Parent location ID (e.g. 'VN-01')
     */
    public static function getLocations(?string $type = null, ?string $parentId = null): array
    {
        // Build a specific cache key so each unique filter combination is cached separately
        $cacheKey = 'ocean_express_locations_' . ($type ?? 'all') . '_' . ($parentId ?? 'root');

        return Cache::remember($cacheKey, 86400, function () use ($type, $parentId) {
            $url = env('OCEAN_EXPRESS_API_URL') . '/locations';

            $params = [];
            if ($type) {
                $params['type'] = $type;
            }
            if ($parentId) {
                $params['parent_id'] = $parentId;
            }

            try {
                $response = Http::get($url, $params);
                if ($response->successful()) {
                    return $response->json('data', []);
                }
                Log::warning('OceanExpress getLocations non-200: ' . $response->status() . ' ' . $response->body());
            } catch (\Exception $e) {
                Log::error('OceanExpress getLocations error: ' . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Calculate shipping rate via Ocean Express.
     * receiver_location_id must be a valid Ocean Express location ID (e.g. 'VN-01-00004').
     */
    public static function calculateRate(string $receiverLocationId, int $weight): int
    {
        $url = env('OCEAN_EXPRESS_API_URL') . '/rates/calculate';

        try {
            $response = Http::withHeaders([
                'X-API-Key'    => env('OCEAN_EXPRESS_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($url, [
                'receiver_location_id' => $receiverLocationId,
                'weight'               => $weight,
            ]);

            if ($response->successful()) {
                return (int) $response->json('data.fee', 30000);
            }

            Log::warning('OceanExpress calculateRate failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('OceanExpress calculateRate error: ' . $e->getMessage());
        }

        return 30000; // Fallback fee
    }

    /**
     * Create a shipment order in Ocean Express.
     * Request body per API spec:
     *   receiver_name, receiver_phone, receiver_location_id,
     *   receiver_address_detail, weight, cod_amount
     */
    public static function createOrder(array $orderData): ?array
    {
        $url = env('OCEAN_EXPRESS_API_URL') . '/orders';

        try {
            $response = Http::withHeaders([
                'X-API-Key'    => env('OCEAN_EXPRESS_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post($url, $orderData);

            if ($response->successful()) {
                // Returns: { id, tracking_number, status, shipping_fee, estimated_delivery_time }
                return $response->json('data');
            }

            Log::error('OceanExpress createOrder failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('OceanExpress createOrder error: ' . $e->getMessage());
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
        $url = env('OCEAN_EXPRESS_API_URL') . '/public/tracking/' . urlencode($trackingNumber);

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('OceanExpress getTracking failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('OceanExpress getTracking error: ' . $e->getMessage());
        }

        return null;
    }
}
