<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ShippingService
{
    /**
     * Tính phí vận chuyển cho một địa chỉ.
     *
     * Việt Nam sau sáp nhập 2025 chỉ còn 2 cấp hành chính (Tỉnh + Phường/Xã),
     * và Ocean Express chỉ cần `ward_code` làm receiver_location_id. Vì vậy
     * KHÔNG được điều kiện hoá theo `district_code` — cột đó nay luôn null, và
     * trước đây điều kiện này khiến mọi đơn đều nhận phí fallback cứng.
     *
     * @param  object  $address  Address model hoặc object có ward_code
     * @param  float  $subtotal  Tạm tính giỏ hàng
     * @param  mixed  $coupon  Coupon đã áp dụng (nếu có)
     * @param  int|null  $weight  Trọng lượng thật của giỏ (gram). Bắt buộc truyền
     *                            để phí báo ở checkout khớp phí ghi vào đơn.
     */
    public function calculateShippingFee($address, float $subtotal, $coupon = null, ?int $weight = null): int
    {
        return $this->quote($address, $subtotal, $coupon, $weight)['fee'];
    }

    /**
     * Như calculateShippingFee nhưng trả về đầy đủ ngữ cảnh để UI hiển thị đúng.
     *
     * @return array{fee: int, free_shipping: bool, reason: string|null}
     */
    public function quote($address, float $subtotal, $coupon = null, ?int $weight = null): array
    {
        $freeshipThreshold = (int) config('shop.freeship_threshold', 500000);

        if ($subtotal >= $freeshipThreshold) {
            return $this->free('freeship_threshold');
        }

        if ($coupon && $coupon->type === 'free_ship') {
            return $this->free('coupon');
        }

        $wardCode = $address->ward_code ?? null;

        if (empty($wardCode)) {
            // Địa chỉ chưa có mã địa điểm Ocean Express (địa chỉ cũ trước sáp
            // nhập). Không thể gọi API — trả phí tạm tính và báo rõ nguyên nhân
            // để checkout yêu cầu khách chọn lại Tỉnh/Xã.
            return [
                'fee' => $this->fallbackFee(),
                'free_shipping' => false,
                'reason' => 'missing_ward_code',
            ];
        }

        $result = $this->getOceanExpressFee($wardCode, $weight);

        return [
            'fee' => $result['fee'],
            'free_shipping' => false,
            'reason' => null,
        ];
    }

    /**
     * Tính tổng trọng lượng giỏ hàng (gram) từ danh sách item.
     * Dùng chung với AdminOrderService để phí báo giá và phí vận đơn khớp nhau.
     */
    public function calculateWeight($items): int
    {
        $defaultWeight = (int) config('ocean_express.default_weight', 500);
        $minWeight = (int) config('ocean_express.min_weight', 100);

        $total = 0;

        foreach ($items as $item) {
            $unitWeight = $item->variant?->product?->weight ?? $defaultWeight;
            $total += (int) $unitWeight * (int) $item->quantity;
        }

        return max($total, $minWeight);
    }

    /**
     * @return array{fee: int}
     */
    private function getOceanExpressFee(string $wardCode, ?int $weight = null): array
    {
        $weight = max(
            (int) ($weight ?: config('ocean_express.default_weight', 500)),
            (int) config('ocean_express.min_weight', 100)
        );

        try {
            return OceanExpressService::calculateRateDetailed($wardCode, $weight);
        } catch (\Throwable $e) {
            Log::error('Ocean Express fee API error: '.$e->getMessage());
        }

        return ['fee' => $this->fallbackFee()];
    }

    private function fallbackFee(): int
    {
        return (int) config('ocean_express.fallback_fee', 30000);
    }

    /**
     * @return array{fee: int, free_shipping: bool, reason: string}
     */
    private function free(string $reason): array
    {
        return ['fee' => 0, 'free_shipping' => true, 'reason' => $reason];
    }
}
