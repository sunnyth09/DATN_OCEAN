<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShippingService
{
    public function calculateShippingFee($address, float $subtotal, $coupon = null): int
    {
        $shippingFee = 30000;

        if (config('ghn.token') && config('ghn.shop_id') && $address->district_code && $address->ward_code) {
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
            $json = GHNService::calculateFee([
                'district_id' => (int) $address->district_code,
                'ward_code' => (string) $address->ward_code,
                'weight' => (int) config('ghn.default_weight', 500),
            ]);

            if (isset($json['data']['total'])) {
                return (int) $json['data']['total'];
            }
        } catch (\Exception $e) {
            Log::error('GHN fee API error: ' . $e->getMessage());
        }

        return 30000;
    }
}
