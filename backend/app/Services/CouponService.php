<?php

namespace App\Services;

use App\Models\UserCoupon;
use App\Repositories\CouponRepository;

class CouponService
{
    public function __construct(
        protected CouponRepository $couponRepository
    ) {}

    public function applyCoupon(int $userId, ?string $couponCode, float $subtotal): array
    {
        if (!$couponCode) {
            return [
                'success' => true,
                'coupon' => null,
                'discount_amount' => 0,
            ];
        }

        $coupon = $this->couponRepository->findActiveByCode($couponCode);

        if (!$coupon) {
            return [
                'success' => true,
                'coupon' => null,
                'discount_amount' => 0,
            ];
        }

        $validateResult = $this->validateCoupon($userId, $coupon, $subtotal);

        if (!$validateResult['success']) {
            return $validateResult;
        }

        $discountAmount = $this->calculateDiscount($coupon, $subtotal);

        return [
            'success' => true,
            'coupon' => $coupon,
            'discount_amount' => min($discountAmount, $subtotal),
        ];
    }

    private function validateCoupon(int $userId, $coupon, float $subtotal): array
    {
        $now = now();

        if ($coupon->start_date && $now->lt($coupon->start_date)) {
            return $this->invalid('Mã giảm giá chưa đến thời gian áp dụng!');
        }

        if ($coupon->end_date && $now->gt($coupon->end_date)) {
            return $this->invalid('Mã giảm giá đã hết hạn!');
        }

        if ($coupon->min_order_value && $subtotal < $coupon->min_order_value) {
            return $this->invalid('Đơn hàng không đạt giá trị tối thiểu để áp dụng mã này!');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $this->invalid('Mã giảm giá đã hết lượt sử dụng!');
        }

        if ($coupon->user_usage_limit !== null && $userId > 0) {
            $userUsedCount = $this->couponRepository->getUserCouponUsedCount(
                $userId,
                $coupon->id
            );

            if ($userUsedCount >= $coupon->user_usage_limit) {
                return $this->invalid('Bạn đã hết lượt sử dụng mã này!');
            }
        }

        return ['success' => true];
    }

    private function calculateDiscount($coupon, float $subtotal): float
    {
        if ($coupon->type === 'percent') {
            $discount = ($subtotal * $coupon->value) / 100;

            if ($coupon->max_discount_value) {
                $discount = min($discount, $coupon->max_discount_value);
            }

            return $discount;
        }

        if ($coupon->type === 'fixed') {
            return min($coupon->value, $subtotal);
        }

        return 0;
    }

    public function markCouponAsUsed(int $userId, $coupon): void
    {
        $this->couponRepository->incrementUsage($coupon);

        if ($userId > 0) {
            $this->couponRepository->increaseUserCouponUsage(
                $userId,
                $coupon->id
            );
        }
    }

    /**
     * Tiêu thụ coupon một cách ATOMIC — phải gọi bên trong DB::transaction() của đơn hàng.
     *
     * Dùng conditional UPDATE để chống race (over-redemption / vượt giới hạn per-user).
     * Ném \App\Exceptions\OrderException nếu coupon đã hết lượt để rollback toàn bộ đơn.
     */
    public function consumeCoupon(int $userId, $coupon): void
    {
        if (!$this->couponRepository->tryConsumeGlobal($coupon->id)) {
            throw new \App\Exceptions\OrderException('Mã giảm giá đã hết lượt sử dụng!');
        }

        if (!$this->couponRepository->tryConsumePerUser($userId, $coupon->id, $coupon->user_usage_limit)) {
            throw new \App\Exceptions\OrderException('Bạn đã hết lượt sử dụng mã này!');
        }
    }

    private function invalid(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'coupon' => null,
            'discount_amount' => 0,
        ];
    }
}