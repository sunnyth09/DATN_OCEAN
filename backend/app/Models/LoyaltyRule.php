<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * LoyaltyRule — Quy tắc Earn/Burn của hệ thống loyalty
 *
 * Keys có sẵn: ORDER_COMPLETE, FIRST_ORDER, REFERRAL, BIRTHDAY, REVIEW,
 *              ABANDONED_CART, REDEEM_DISCOUNT
 */
class LoyaltyRule extends Model
{
    protected $table = 'loyalty_rules';

    protected $fillable = [
        'key',
        'type',
        'name',
        'description',
        'points_per_unit',
        'vnd_per_point',
        'min_points',
        'max_points_per_order',
        'max_burn_percent',
        'earn_expiry_days',
        'is_active',
    ];

    protected $casts = [
        'points_per_unit'      => 'float',
        'vnd_per_point'        => 'float',
        'min_points'           => 'integer',
        'max_points_per_order' => 'integer',
        'max_burn_percent'     => 'float',
        'earn_expiry_days'     => 'integer',
        'is_active'            => 'boolean',
    ];

    // ─── Scopes ────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEarn(Builder $query): Builder
    {
        return $query->where('type', 'earn');
    }

    public function scopeBurn(Builder $query): Builder
    {
        return $query->where('type', 'burn');
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Tính ngày hết hạn dựa trên earn_expiry_days (từ thời điểm hiện tại)
     */
    public function calcExpiryDate(): ?\Carbon\Carbon
    {
        if (!$this->earn_expiry_days) return null;
        return now()->addDays($this->earn_expiry_days);
    }

    /**
     * Cache rule theo key (tránh N+1 query)
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->where('is_active', true)->first();
    }
}
