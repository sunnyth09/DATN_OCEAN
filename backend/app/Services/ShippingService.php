<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public function calculateShippingFee($address, float $subtotal, $coupon = null): int
    {
        $shippingFee = 30000;

        if (
            env('VITE_TOKEN_GHN') &&
            env('VITE_SHOPID_GHN') &&
            $address->district_code &&
            $address->ward_code
        ) {
            $shippingFee = $this->getGHNFee($address);
        }

        $freeshipThreshold = (int) config('shop.freeship_threshold', 500000);

        if ($subtotal >= $freeshipThreshold) {
            return 0;
        }

        if ($coupon && $coupon->type === 'free_ship') {
            return 0;
        }

        return $shippingFee;
    }

    private function getGHNFee($address): int
    {
        try {
            $response = Http::withHeaders([
                'Token' => env('VITE_TOKEN_GHN'),
                'ShopId' => env('VITE_SHOPID_GHN'),
            ])->get('https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee', [
                'service_type_id' => 2,
                'to_district_id' => (int) $address->district_code,
                'to_ward_code' => $address->ward_code,
                'weight' => 3000,
            ]);

            if ($response->successful()) {
                $json = $response->json();

                if (isset($json['data']['total'])) {
                    return (int) $json['data']['total'];
                }
            }
        } catch (\Exception $e) {
            Log::error('GHN Fee API Error: ' . $e->getMessage());
        }

        return 30000;
    }
}