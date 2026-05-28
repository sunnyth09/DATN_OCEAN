<?php

namespace App\Repositories;

use App\Models\Coupon;
use App\Models\UserCoupon;

class CouponRepository
{
    public function findActiveByCode(string $code)
    {
        return Coupon::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function getUserCouponUsedCount(int $userId, int $couponId): int
    {
        return UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->value('used_count') ?? 0;
    }

    public function incrementUsage(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    public function increaseUserCouponUsage(int $userId, int $couponId): void
    {
        $userCoupon = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->first();

        if ($userCoupon) {
            $userCoupon->increment('used_count');
            return;
        }

        UserCoupon::create([
            'user_id' => $userId,
            'coupon_id' => $couponId,
            'used_count' => 1,
            'is_saved' => false,
        ]);
    }
}