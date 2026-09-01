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
     * Create a shipment order in Ocean Express with detailed error reporting.
     * Request body per API spec:
     *   receiver_name, receiver_phone, receiver_location_id,
     *   receiver_address_detail, weight, cod_amount
     *
     * @return array{success: bool, data?: array, tracking_number?: string, error?: string, status_code?: int}
     */
    public static function createOrderDetailed(array $orderData): array
    {
        try {
            $response = self::client()->post(self::url('orders'), $orderData);

            if ($response->successful()) {
                $payload = $response->json('data') ?? $response->json();
                $trackingNumber = $payload['tracking_number'] ?? $payload['tracking_code'] ?? $payload['code'] ?? null;

                if ($trackingNumber) {
                    return [
                        'success' => true,
                        'data' => $payload,
                        'tracking_number' => $trackingNumber,
                    ];
                }

                Log::warning('OceanExpress createOrder 200 but missing tracking_number: '.$response->body());
                return [
                    'success' => false,
                    'status_code' => $response->status(),
                    'error' => 'Phản hồi từ Ocean Express thành công nhưng không tìm thấy mã vận đơn (tracking_number).',
                    'raw' => $response->json(),
                ];
            }

            $statusCode = $response->status();
            $body = $response->json() ?? [];
            $rawMsg = $body['message'] ?? $body['error'] ?? null;

            // Thu thập các lỗi validation chi tiết từ API
            if (isset($body['errors']) && is_array($body['errors'])) {
                $fieldErrors = [];
                foreach ($body['errors'] as $field => $messages) {
                    $msgStr = is_array($messages) ? implode(', ', $messages) : (string) $messages;
                    $fieldErrors[] = "{$field}: {$msgStr}";
                }
                $rawMsg = implode(' | ', $fieldErrors);
            }

            if (empty($rawMsg)) {
                $rawMsg = $response->body() ?: "Mã phản hồi HTTP {$statusCode}";
            }

            // Dịch các thông báo lỗi phổ biến sang tiếng Việt rõ ràng
            $friendlyMsg = self::formatApiErrorMessage($rawMsg, $statusCode);

            Log::error("OceanExpress createOrder failed [HTTP {$statusCode}]: {$rawMsg}", [
                'payload' => $orderData,
                'response' => $body,
            ]);

            return [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $friendlyMsg,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('OceanExpress createOrder error: '.$e->getMessage(), [
                'payload' => $orderData,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status_code' => 500,
                'error' => 'Không thể kết nối đến máy chủ Ocean Express: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Dịch thông báo lỗi thô từ API sang tiếng Việt dễ hiểu
     */
    private static function formatApiErrorMessage(string $rawMsg, int $statusCode): string
    {
        if ($statusCode === 401 || str_contains(strtolower($rawMsg), 'unauthenticated')) {
            return 'Lỗi xác thực (401): OCEAN_EXPRESS_API_KEY chưa cấu hình hoặc không hợp lệ.';
        }
        if ($statusCode === 403) {
            return 'Lỗi phân quyền (403): API Key không có quyền tạo đơn vận chuyển trên Ocean Express.';
        }
        if (str_contains($rawMsg, 'receiver_location_id')) {
            return 'Mã địa chỉ (ward_code/receiver_location_id) không hợp lệ hoặc không tồn tại trên hệ thống Ocean Express.';
        }
        if (str_contains($rawMsg, 'receiver_phone')) {
            return 'Số điện thoại người nhận không hợp lệ (cần 10 chữ số).';
        }
        if (str_contains($rawMsg, 'receiver_name')) {
            return 'Tên người nhận hàng không được để trống.';
        }
        if (str_contains($rawMsg, 'weight')) {
            return 'Trọng lượng gói hàng không hợp lệ (phải lớn hơn 0 gram).';
        }
        if ($statusCode >= 500) {
            return "Máy chủ Ocean Express gặp sự cố nội bộ (HTTP {$statusCode}): {$rawMsg}";
        }

        return "Ocean Express báo lỗi (HTTP {$statusCode}): {$rawMsg}";
    }

    /**
     * Create a shipment order in Ocean Express (Tương thích ngược).
     */
    public static function createOrder(array $orderData): ?array
    {
        $res = self::createOrderDetailed($orderData);
        return $res['success'] ? $res['data'] : null;
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

    /**
     * Get Print Label URL for Ocean Express order.
     */
    public static function printLabel(string $trackingNumber): array
    {
        $directUrl = self::url('public/tracking/'.urlencode($trackingNumber).'/label');

        try {
            $response = self::client(false)->get(self::url('public/orders/'.urlencode($trackingNumber).'/print-label'));
            if ($response->successful()) {
                $data = $response->json('data', []);
                $labelUrl = $data['label_url'] ?? $data['pdf_url'] ?? $directUrl;

                if (str_contains($labelUrl, '/public/orders/')) {
                    $labelUrl = str_replace('/public/orders/', '/public/tracking/', $labelUrl);
                }

                return [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Lấy link in vận đơn thành công',
                    'data' => [
                        'token' => 'oe_'.md5($trackingNumber),
                        'print_url' => $labelUrl,
                        'label_url' => $labelUrl,
                        'pdf_url' => $labelUrl,
                        'tracking_number' => $trackingNumber,
                    ],
                ];
            }

            return [
                'code' => 200,
                'status' => 'success',
                'message' => 'Lấy link in vận đơn thành công',
                'data' => [
                    'token' => 'oe_'.md5($trackingNumber),
                    'print_url' => $directUrl,
                    'label_url' => $directUrl,
                    'pdf_url' => $directUrl,
                    'tracking_number' => $trackingNumber,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('OceanExpress printLabel error: '.$e->getMessage());

            return [
                'code' => 200,
                'status' => 'success',
                'message' => 'Lấy link in vận đơn thành công',
                'data' => [
                    'token' => 'oe_'.md5($trackingNumber),
                    'print_url' => $directUrl,
                    'label_url' => $directUrl,
                    'pdf_url' => $directUrl,
                    'tracking_number' => $trackingNumber,
                ],
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
                    'message' => 'Hủy vận đơn Ocean Express thành công',
                    'data' => $response->json('data', []),
                ];
            }

            Log::warning('OceanExpress cancelOrder failed: '.$response->body());

            return [
                'code' => $response->status(),
                'status' => 'error',
                'message' => $response->json('message') ?? 'Không thể hủy đơn Ocean Express',
            ];
        } catch (\Throwable $e) {
            Log::error('OceanExpress cancelOrder error: '.$e->getMessage());

            return [
                'code' => 500,
                'status' => 'error',
                'message' => 'Lỗi kết nối khi hủy đơn Ocean Express',
            ];
        }
    }
}
