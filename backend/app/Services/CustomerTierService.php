<?php

namespace App\Services;

use App\Models\CustomerTier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CustomerTierService
{
    /**
     * Tích lũy chi tiêu và thăng hạng khi đơn hàng hoàn thành
     */
    public function addSpendingAndUpgradeTier(User $user, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        try {
            $user->total_spent += $amount;
            $user->tier_month_spent += $amount;
            
            // Tìm hạng cao nhất phù hợp với tier_month_spent hiện tại
            $newTier = CustomerTier::where('is_active', true)
                ->where('min_spent', '<=', $user->tier_month_spent)
                ->orderByDesc('min_spent')
                ->first();

            if ($newTier) {
                // Nếu đạt hạng mới thì cập nhật
                if ($user->tier_id !== $newTier->id) {
                    $user->tier_id = $newTier->id;
                    Log::info("User {$user->user_id} upgraded to tier {$newTier->name}");
                }
            }

            // Dùng forceFill để vượt qua $guarded nếu có
            $user->forceFill([
                'total_spent' => $user->total_spent,
                'tier_month_spent' => $user->tier_month_spent,
                'tier_id' => $user->tier_id,
            ])->save();

        } catch (\Exception $e) {
            Log::error("Failed to upgrade tier for user {$user->user_id}: " . $e->getMessage());
        }
    }

    /**
     * Giảm chi tiêu (khi đơn hàng bị hoàn/trả)
     */
    public function subtractSpendingAndDowngradeTier(User $user, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        try {
            $user->total_spent = max(0, $user->total_spent - $amount);
            $user->tier_month_spent = max(0, $user->tier_month_spent - $amount);
            
            // Tìm hạng cao nhất phù hợp với tier_month_spent bị giảm
            $newTier = CustomerTier::where('is_active', true)
                ->where('min_spent', '<=', $user->tier_month_spent)
                ->orderByDesc('min_spent')
                ->first();

            $newTierId = $newTier ? $newTier->id : null;

            if ($user->tier_id !== $newTierId) {
                $user->tier_id = $newTierId;
                Log::info("User {$user->user_id} downgraded to tier " . ($newTier->name ?? 'None'));
            }

            $user->forceFill([
                'total_spent' => $user->total_spent,
                'tier_month_spent' => $user->tier_month_spent,
                'tier_id' => $user->tier_id,
            ])->save();

        } catch (\Exception $e) {
            Log::error("Failed to downgrade tier for user {$user->user_id}: " . $e->getMessage());
        }
    }
}
