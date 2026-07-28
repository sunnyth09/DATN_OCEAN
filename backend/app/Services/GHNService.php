<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GHNService
{
    private static function client(bool $includeShopId = true): PendingRequest
    {
        $headers = [
            'Token' => (string) config('ghn.token'),
            'Content-Type' => 'application/json',
        ];

        if ($includeShopId) {
            $headers['ShopId'] = (string) config('ghn.shop_id');
        }

        return Http::withHeaders($headers)->timeout((int) config('ghn.timeout', 10));
    }

    private static function url(string $path): string
    {
        return rtrim((string) config('ghn.base_url', 'https://online-gateway.ghn.vn'), '/') . '/' . ltrim($path, '/');
    }

    private static function ensureConfigured(): void
    {
        $token = config('ghn.token');
        $shopId = config('ghn.shop_id');

        if (!$token || !$shopId) {
            throw new \Exception('Chưa cấu hình GHN_TOKEN hoặc GHN_SHOP_ID trong .env');
        }

        // Bổ sung config nếu bị mất cache
        config([
            'ghn.token' => $token,
            'ghn.shop_id' => $shopId,
        ]);
    }

    public static function getProvinces(): array
    {
        self::ensureConfigured();

        $response = self::client(false)->get(self::url('/shiip/public-api/master-data/province'));

        if (!$response->successful()) {
            Log::warning('GHN provinces request failed', ['status' => $response->status()]);
            return [];
        }

        return $response->json('data') ?? [];
    }

    public static function getDistricts(int $provinceId): array
    {
        self::ensureConfigured();

        $response = self::client(false)->get(self::url('/shiip/public-api/master-data/district'), [
            'province_id' => $provinceId,
        ]);

        if (!$response->successful()) {
            Log::warning('GHN districts request failed', ['province_id' => $provinceId, 'status' => $response->status()]);
            return [];
        }

        return $response->json('data') ?? [];
    }

    public static function getWards(int $districtId): array
    {
        self::ensureConfigured();

        $response = self::client(false)->get(self::url('/shiip/public-api/master-data/ward'), [
            'district_id' => $districtId,
        ]);

        if (!$response->successful()) {
            Log::warning('GHN wards request failed', ['district_id' => $districtId, 'status' => $response->status()]);
            return [];
        }

        return $response->json('data') ?? [];
    }

    public static function calculateFee(array $data): array
    {
        self::ensureConfigured();

        $payload = [
            'service_type_id' => (int) ($data['service_type_id'] ?? config('ghn.service_type_id', 2)),
            'to_district_id' => (int) ($data['district_id'] ?? $data['to_district_id']),
            'to_ward_code' => (string) ($data['ward_code'] ?? $data['to_ward_code']),
            'weight' => max((int) ($data['weight'] ?? config('ghn.default_weight', 500)), (int) config('ghn.min_weight', 10)),
        ];

        $response = self::client()->post(self::url('/shiip/public-api/v2/shipping-order/fee'), $payload);

        if (!$response->successful()) {
            Log::warning('GHN fee request failed', [
                'status' => $response->status(),
                'to_district_id' => $payload['to_district_id'],
                'to_ward_code' => $payload['to_ward_code'],
            ]);
        }

        return $response->json() ?? ['code' => $response->status(), 'message' => 'GHN fee request failed'];
    }

    /**
     * Tạo đơn hàng giao hàng nhanh (GHN)
     */
    public static function createOrder($order)
    {
        self::ensureConfigured();

        $order->loadMissing(['items.product', 'address']);
        $address = $order->address;
        $toDistrictId = $address?->district_code ?? $order->district_code;
        $toWardCode = $address?->ward_code ?? $order->ward_code;
        $toAddress = $address?->address_line ?: $order->shipping_address;
        $toName = $order->recipient_name ?: ($address?->recipient_name ?? 'Khách Hàng');
        $toPhone = $order->recipient_phone ?: ($address?->phone ?? '');

        if (!$toDistrictId || !$toWardCode) {
            throw new \Exception('Địa chỉ đơn hàng chưa có mã Quận/Huyện hoặc Phường/Xã chuẩn GHN');
        }

        $sender = config('ghn.sender');
        if (empty($sender['phone']) || empty($sender['address']) || empty($sender['ward_code']) || empty($sender['district_id'])) {
            throw new \Exception('Chưa cấu hình đầy đủ địa chỉ kho gửi GHN');
        }
        $items = [];
        $totalWeight = 0;
        $defaultWeight = (int) config('ghn.default_weight', 500);
        $minWeight = (int) config('ghn.min_weight', 10);

        foreach ($order->items as $item) {
            $weight = (int) ($item->product?->weight ?? $defaultWeight);
            $weight = max($weight, $minWeight);
            $quantity = (int) $item->quantity;
            $totalWeight += $weight * $quantity;

            $items[] = [
                'name' => trim($item->product_name . ($item->variant_name ? ' - ' . $item->variant_name : '')),
                'quantity' => $quantity,
                'price' => (int) $item->unit_price,
                'weight' => $weight,
            ];
        }

        $payload = [
            'payment_type_id' => $order->payment_method === 'cod' ? 2 : 1,
            'service_type_id' => (int) config('ghn.service_type_id', 2),
            'required_note' => (string) config('ghn.required_note', 'KHONGCHOXEMHANG'),

            'to_name' => $toName,
            'to_phone' => $toPhone,
            'to_address' => $toAddress,
            'to_ward_code' => (string) $toWardCode,
            'to_district_id' => (int) $toDistrictId,
            'weight' => max($totalWeight, $minWeight),
            'length' => 1,
            'width' => 19,
            'height' => 10,
            'items' => $items,
        ];

        if (!empty($sender['name'])) $payload['from_name'] = (string) $sender['name'];
        if (!empty($sender['phone'])) $payload['from_phone'] = (string) $sender['phone'];
        if (!empty($sender['address'])) $payload['from_address'] = (string) $sender['address'];
        if (!empty($sender['ward_code'])) $payload['from_ward_code'] = (string) $sender['ward_code'];
        if (!empty($sender['district_id'])) $payload['from_district_id'] = (int) $sender['district_id'];


        try {
            $response = self::client()->post(self::url('/shiip/public-api/v2/shipping-order/create'), $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GHN create order failed', [
                'order_id' => $order->order_id ?? null,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Lỗi từ GHN: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('GHN create order exception', [
                'order_id' => $order->order_id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public static function createReturnOrder(ReturnRequest $returnRequest): array
    {
        self::ensureConfigured();

        $returnRequest->loadMissing(['items.orderItem.product', 'items.product', 'order']);

        $fromDistrictId = $returnRequest->return_pickup_district_code ?: $returnRequest->order?->district_code;
        $fromWardCode = $returnRequest->return_pickup_ward_code ?: $returnRequest->order?->ward_code;
        $fromAddress = $returnRequest->return_pickup_address ?: $returnRequest->order?->shipping_address;
        $fromName = $returnRequest->return_pickup_name ?: ($returnRequest->order?->recipient_name ?? 'Khách Hàng');
        $fromPhone = $returnRequest->return_pickup_phone ?: ($returnRequest->order?->recipient_phone ?? '');

        if (!$fromDistrictId || !$fromWardCode || !$fromAddress || !$fromPhone) {
            throw new \Exception('Địa chỉ lấy hàng hoàn chưa đủ thông tin GHN.');
        }

        $sender = config('ghn.sender');
        if (empty($sender['phone']) || empty($sender['address']) || empty($sender['ward_code']) || empty($sender['district_id'])) {
            throw new \Exception('Chưa cấu hình đầy đủ địa chỉ kho nhận hàng hoàn GHN');
        }

        $items = [];
        $totalWeight = 0;
        $defaultWeight = (int) config('ghn.default_weight', 500);
        $minWeight = (int) config('ghn.min_weight', 10);

        foreach ($returnRequest->items as $item) {
            $orderItem = $item->orderItem;
            $product = $item->product ?: $orderItem?->product;
            $weight = max((int) ($product?->weight ?? $defaultWeight), $minWeight);
            $quantity = max((int) $item->requested_quantity, 1);
            $totalWeight += $weight * $quantity;

            $items[] = [
                'name' => trim(($orderItem?->product_name ?? $product?->name ?? 'Sản phẩm hoàn') . ($orderItem?->variant_name ? ' - ' . $orderItem->variant_name : '')),
                'quantity' => $quantity,
                'price' => (int) ($item->unit_price ?? $orderItem?->unit_price ?? 0),
                'weight' => $weight,
            ];
        }

        $payload = [
            'payment_type_id' => 1,
            'service_type_id' => (int) config('ghn.service_type_id', 2),
            'required_note' => (string) config('ghn.required_note', 'KHONGCHOXEMHANG'),
            'from_name' => $fromName,
            'from_phone' => $fromPhone,
            'from_address' => $fromAddress,
            'from_ward_code' => (string) $fromWardCode,
            'from_district_id' => (int) $fromDistrictId,
            'to_name' => (string) ($sender['name'] ?? 'Kho OCEAN'),
            'to_phone' => (string) $sender['phone'],
            'to_address' => (string) $sender['address'],
            'to_ward_code' => (string) $sender['ward_code'],
            'to_district_id' => (int) $sender['district_id'],
            'weight' => max($totalWeight, $minWeight),
            'length' => 1,
            'width' => 19,
            'height' => 10,
            'items' => $items,
        ];

        try {
            $response = self::client()->post(self::url('/shiip/public-api/v2/shipping-order/create'), $payload);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::error('GHN create return order failed', [
                'return_request_id' => $returnRequest->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Lỗi từ GHN khi tạo vận đơn hoàn: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('GHN create return order exception', [
                'return_request_id' => $returnRequest->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public static function calculateLeadtime($data)
    {
        self::ensureConfigured();

        try {
            $sender = config('ghn.sender');
            $payload = [
                'to_district_id' => (int) ($data['district_id'] ?? $data['to_district_id']),
                'to_ward_code' => (string) ($data['ward_code'] ?? $data['to_ward_code']),
                'service_id' => isset($data['service_id']) ? (int) $data['service_id'] : null,
                'service_type_id' => (int) config('ghn.service_type_id', 2),
            ];

            if (!empty($sender['district_id'])) $payload['from_district_id'] = (int) $sender['district_id'];
            if (!empty($sender['ward_code'])) $payload['from_ward_code'] = (string) $sender['ward_code'];

            $response = self::client()->post(self::url('/shiip/public-api/v2/shipping-order/leadtime'), $payload);


            return $response->json();
        } catch (\Exception $e) {
            Log::error('GHN leadtime error', ['error' => $e->getMessage()]);
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }

    public static function cancelOrder($orderCode)
    {
        self::ensureConfigured();

        try {
            $response = self::client()->post(self::url('/shiip/public-api/v2/switch-status/cancel'), [
                'order_codes' => [$orderCode],
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GHN cancel order error', ['order_code' => $orderCode, 'error' => $e->getMessage()]);
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }

    public static function printLabel($orderCode)
    {
        self::ensureConfigured();

        try {
            $response = self::client(false)->post(self::url('/a5/public-api/printA5/gen-token'), [
                'order_codes' => [$orderCode],
            ]);

            $json = $response->json();
            if (isset($json['data']['token'])) {
                $json['data']['print_url'] = self::url('/a5/public-api/printA5?token=' . $json['data']['token']);
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('GHN print label error', ['order_code' => $orderCode, 'error' => $e->getMessage()]);
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }

    public static function getOrderDetail(string $orderCode): array
    {
        self::ensureConfigured();

        try {
            $response = self::client()->post(self::url('/shiip/public-api/v2/shipping-order/detail'), [
                'order_code' => $orderCode,
            ]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::warning('GHN order detail request failed', ['order_code' => $orderCode, 'status' => $response->status()]);
        } catch (\Exception $e) {
            Log::warning('GHN order detail exception', ['order_code' => $orderCode, 'error' => $e->getMessage()]);
        }

        return [];
    }
}
