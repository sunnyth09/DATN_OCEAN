<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LoyaltyTransaction — Giao dịch điểm của user
 *
 * @property int $id
 * @property int $user_id
 * @property string $type earn|burn|expire|adjust|refund
 * @property int $points số điểm (luôn dương)
 * @property int $balance_before
 * @property int $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property Carbon|null $expires_at
 * @property Carbon|null $expired_at
 */
class LoyaltyTransaction extends Model
{
    protected $table = 'loyalty_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'expires_at',
        'expired_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeEarned(Builder $query): Builder
    {
        return $query->whereIn('type', ['earn', 'adjust', 'refund']);
    }

    public function scopeBurned(Builder $query): Builder
    {
        return $query->where('type', 'burn');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('type', 'expire');
    }

    /** Điểm earn chưa hết hạn và chưa bị expire */
    public function scopeActiveEarns(Builder $query): Builder
    {
        return $query->where('type', 'earn')
            ->whereNull('expired_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /** Điểm earn đã quá hạn nhưng chưa được job expire */
    public function scopePendingExpiry(Builder $query): Builder
    {
        return $query->where('type', 'earn')
            ->whereNull('expired_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'earn' => 'Tích điểm',
            'burn' => 'Đổi điểm',
            'expire' => 'Hết hạn',
            'adjust' => 'Điều chỉnh',
            'refund' => 'Hoàn điểm',
            default => $this->type,
        };
    }
}
