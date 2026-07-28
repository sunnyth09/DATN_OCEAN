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

        // Các field affiliate là $guarded (không mass-assignable) nên phải forceFill.
        $user->forceFill([
            'referral_code' => $referralCode,
            'is_affiliate' => true,
            'affiliate_registered_at' => now(),
        ])->save();

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

    /**
     * Danh sách tất cả affiliate cho Admin
     */
    public function adminListAffiliates(int $perPage = 10)
    {
        return User::where('is_affiliate', true)
            ->with(['wallet'])
            ->withCount(['affiliateConversions as total_conversions'])
            ->withSum('affiliateConversions as total_commission', 'commission_amount')
            ->orderBy('affiliate_registered_at', 'desc')
            ->paginate($perPage);
    }
}
