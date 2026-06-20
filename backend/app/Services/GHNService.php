<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GHNService
{
    /**
     * Tạo đơn hàng giao hàng nhanh (GHN)
     */
    public static function createOrder($order)
    {
        $token = env('VITE_TOKEN_GHN_SANBOX', '');
        $shopId = env('VITE_SHOPID_GHN_SANBOX', '');

        if (empty($token) || empty($shopId)) {
            throw new \Exception('Chưa cấu hình VITE_TOKEN_GHN_SANBOX hoặc VITE_SHOPID_GHN_SANBOX trong .env');
        }

        $url = 'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create';

        // Lấy thông tin địa chỉ từ order
        $address = $order->address;
        if (!$address) {
            // Lấy trực tiếp từ order nếu không có relation address
            $toWardCode = '20308'; // Default fallback
            $toDistrictId = 1444; // Default fallback
            $toAddress = $order->shipping_address ?? 'Không xác định';
        } else {
            $toWardCode = $address->ward_code ?: '20308';
            $toDistrictId = (int)($address->district_code ?: 1444);
            $toAddress = implode(', ', array_filter([
                $address->address_line,
                $address->ward,
                $address->district,
                $address->province
            ]));
        }

        // Tạo mảng items
        $items = [];
        $totalWeight = 0;
        foreach ($order->items as $item) {
            $weight = 1200; // Mặc định 1.2kg mỗi sản phẩm nếu không có data thật
            $totalWeight += $weight * $item->quantity;

            $items[] = [
                'name' => $item->product_name . ' - ' . $item->variant_name,
                'quantity' => (int)$item->quantity,
                'price' => (int)$item->unit_price,
                'weight' => $weight
            ];
        }

        if ($totalWeight == 0) {
            $totalWeight = 200; // default
        }

        $payload = [
            'payment_type_id' => 2,
            'service_type_id' => 2,
            'required_note' => 'KHONGCHOXEMHANG',
            'from_name' => 'Ocean',
            'from_phone' => '0355386141',
            'from_address' => '72 Thành Thái, Phường 14, Quận 10, Hồ Chí Minh, Vietnam',
            'from_ward_name' => 'Phường 14',
            'from_district_name' => 'Quận 10',
            'from_province_name' => 'HCM',
            'to_name' => collect([$order->recipient_name])->first() ?? 'Khách Hàng',
            'to_phone' => collect([$order->recipient_phone])->first() ?? '0987654321',
            'to_address' => $toAddress,
            'to_ward_code' => (string)$toWardCode,
            'to_district_id' => $toDistrictId,
            'weight' => $totalWeight,
            'length' => 1,
            'width' => 19,
            'height' => 10,
            'items' => $items
        ];

        try {
            $response = Http::withHeaders([
                'Token' => $token,
                'ShopId' => $shopId,
            ])->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('GHN Create Order Failed: ' . $response->body());
                throw new \Exception('Lỗi từ GHN: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('GHN Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function calculateLeadtime($data)
    {
        $token = env('VITE_TOKEN_GHN_SANBOX', '');
        $shopId = env('VITE_SHOPID_GHN_SANBOX', '');

        try {
            $response = Http::withHeaders([
                'Token' => $token,
                'ShopId' => $shopId,
            ])->post('https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/leadtime', [
                'from_district_id' => 1444, // Default shop district if not provided
                'from_ward_code' => '20308', // Default shop ward
                'to_district_id' => (int)$data['to_district_id'],
                'to_ward_code' => (string)$data['to_ward_code'],
                'service_id' => 53320, // Default service ID or passed from data
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GHN Leadtime Error: ' . $e->getMessage());
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }

    public static function cancelOrder($orderCode)
    {
        $token = env('VITE_TOKEN_GHN_SANBOX', '');
        $shopId = env('VITE_SHOPID_GHN_SANBOX', '');

        try {
            $response = Http::withHeaders([
                'Token' => $token,
                'ShopId' => $shopId,
            ])->post('https://dev-online-gateway.ghn.vn/shiip/public-api/v2/switch-status/cancel', [
                'order_codes' => [$orderCode]
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GHN Cancel Order Error: ' . $e->getMessage());
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }

    public static function printLabel($orderCode)
    {
        $token = env('VITE_TOKEN_GHN_SANBOX', '');

        try {
            $response = Http::withHeaders([
                'Token' => $token,
            ])->post('https://dev-online-gateway.ghn.vn/a5/public-api/printA5/gen-token', [
                'order_codes' => [$orderCode]
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('GHN Print Label Error: ' . $e->getMessage());
            return ['code' => 500, 'message' => 'Internal Server Error'];
        }
    }
}
