<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WalletTransaction — Giao dịch ví của user.
 *
 * @property int $transaction_id
 * @property int $wallet_id
 * @property string $transaction_code WTX-xxxx (unique idempotency key)
 * @property string $type deposit|commission|refund|loyalty_convert|promo_credit|order_discount|booking_payment|adjustment
 * @property string $balance_type deposit|commission
 * @property string $direction credit|debit
 * @property float $amount
 * @property float $balance_before
 * @property float $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $description
 * @property string $status pending|completed|failed|cancelled
 * @property array|null $metadata
 */
class WalletTransaction extends Model
{
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'wallet_id',
        'transaction_code',
        'type',
        'balance_type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'wallet_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('direction', 'debit');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByBalanceType(Builder $query, string $balanceType): Builder
    {
        return $query->where('balance_type', $balanceType);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Label tiếng Việt cho loại giao dịch.
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'deposit' => 'Nạp tiền',
            'commission' => 'Hoa hồng affiliate',
            'refund' => 'Hoàn tiền',
            'loyalty_convert' => 'Quy đổi điểm',
            'promo_credit' => 'Khuyến mãi',
            'order_discount' => 'Giảm giá đơn hàng',
            'withdrawal' => 'Rút tiền',
            'booking_payment' => 'Thanh toán đặt sân',
            'adjustment' => 'Điều chỉnh',
            default => $this->type,
        };
    }

    /**
     * Icon cho frontend.
     */
    public function typeIcon(): string
    {
        return match ($this->type) {
            'deposit' => '💳',
            'commission' => '🤝',
            'refund' => '🎁',
            'loyalty_convert' => '💎',
            'promo_credit' => '🎉',
            'order_discount' => '🛒',
            'withdrawal' => '🏧',
            'booking_payment' => '⛳',
            'adjustment' => '⚙️',
            default => '💰',
        };
    }

    /**
     * Dấu +/- cho hiển thị.
     */
    public function getSignAttribute(): string
    {
        return $this->direction === 'credit' ? '+' : '-';
    }
}
