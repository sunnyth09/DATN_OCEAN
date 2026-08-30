<?php

namespace App\Repositories;

use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Tiêu thụ 1 lượt dùng coupon toàn cục một cách atomic.
     *
     * Dùng conditional UPDATE (used_count < usage_limit) để chống race:
     * chỉ tăng khi còn lượt. Trả về false nếu đã hết lượt.
     * PHẢI gọi bên trong DB::transaction() của đơn hàng.
     */
    public function tryConsumeGlobal(int $couponId): bool
    {
        $affected = Coupon::where('id', $couponId)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->update(['used_count' => DB::raw('used_count + 1')]);

        return $affected > 0;
    }

    /**
     * Tiêu thụ 1 lượt dùng coupon theo từng user một cách atomic.
     *
     * $perUserLimit = null nghĩa là không giới hạn theo user.
     * Trả về false nếu user đã hết lượt. PHẢI gọi trong DB::transaction().
     */
    public function tryConsumePerUser(int $userId, int $couponId, ?int $perUserLimit): bool
    {
        if ($userId <= 0) {
            return true;
        }

        if ($perUserLimit !== null && $perUserLimit <= 0) {
            return false;
        }

        // Tăng nếu bản ghi đã tồn tại và còn lượt (hoặc không giới hạn)
        $query = UserCoupon::where('user_id', $userId)->where('coupon_id', $couponId);
        if ($perUserLimit !== null) {
            $query->where('used_count', '<', $perUserLimit);
        }
        $affected = $query->update(['used_count' => DB::raw('used_count + 1')]);

        if ($affected > 0) {
            return true;
        }

        // Chưa có bản ghi nào cho user này → kiểm tra đã tồn tại chưa để phân biệt
        // "hết lượt" (đã có row nhưng bị chặn bởi điều kiện) với "lần dùng đầu tiên".
        $exists = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            // Có row nhưng update không tác động → đã chạm giới hạn per-user
            return false;
        }

        UserCoupon::create([
            'user_id' => $userId,
            'coupon_id' => $couponId,
            'used_count' => 1,
            'is_saved' => false,
        ]);

        return true;
    }
}
