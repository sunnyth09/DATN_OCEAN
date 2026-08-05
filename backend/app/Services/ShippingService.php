<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShippingService
{
    public function calculateShippingFee($address, float $subtotal, $coupon = null): int
    {
        $shippingFee = 30000;

        if ($address->district_code && $address->ward_code) {
            $shippingFee = $this->getOceanExpressFee($address);
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

    private function getOceanExpressFee($address): int
    {
        try {
            return \App\Services\OceanExpressService::calculateRate(
                $address->ward_code,
                config('ghn.default_weight', 500)
            );
        } catch (\Exception $e) {
            Log::error('Ocean Express fee API error: '.$e->getMessage());
        }

        return 30000;
    }
}
