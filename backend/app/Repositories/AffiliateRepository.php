<?php

namespace App\Repositories;

use App\Models\User;

class AffiliateRepository
{
    /**
     * Tìm user bằng referral code
     */
    public function findByReferralCode(string $code): ?User
    {
        return User::where('referral_code', $code)
            ->where('is_affiliate', true)
            ->first();
    }

    /**
     * Cập nhật user thành affiliate
     */
    public function updateUserAsAffiliate(int $userId, string $referralCode): User
    {
        $user = User::where('user_id', $userId)->firstOrFail();

        $user->update([
            'referral_code' => $referralCode,
            'is_affiliate' => true,
            'affiliate_registered_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Kiểm tra referral code đã tồn tại chưa
     */
    public function referralCodeExists(string $code): bool
    {
        return User::where('referral_code', $code)->exists();
    }

    /**
     * Lấy thông tin affiliate profile
     */
    public function getAffiliateProfile(int $userId): ?User
    {
        return User::where('user_id', $userId)
            ->select('user_id', 'full_name', 'email', 'referral_code', 'is_affiliate', 'affiliate_registered_at')
            ->first();
    }
}
