<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wallet — Ví cá nhân của user.
 *
 * Có 2 loại số dư tách biệt:
 * - deposit_balance:    Tiền nạp trực tiếp + refund + loyalty convert
 * - commission_balance: Hoa hồng affiliate (giới hạn dùng 10%/đơn)
 *
 * @property int $wallet_id
 * @property int $user_id
 * @property float $deposit_balance
 * @property float $commission_balance
 * @property float $frozen_balance
 * @property float $total_deposited
 * @property float $total_commission
 * @property float $total_used
 * @property string $status active|frozen|closed
 * @property string|null $pin_hash
 */
class Wallet extends Model
{
    protected $primaryKey = 'wallet_id';

    protected $fillable = [
        'user_id',
        'deposit_balance',
        'commission_balance',
        'frozen_balance',
        'total_deposited',
        'total_commission',
        'total_used',
        'status',
        'pin_hash',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'deposit_balance' => 'decimal:2',
            'commission_balance' => 'decimal:2',
            'frozen_balance' => 'decimal:2',
            'total_deposited' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'total_used' => 'decimal:2',
        ];
    }

    // ─── Computed Balances ───────────────────────────────────────────────

    /**
     * Tổng số dư hiển thị (deposit + commission).
     */
    public function getTotalBalance(): float
    {
        return (float) $this->deposit_balance + (float) $this->commission_balance;
    }

    /**
     * Số dư khả dụng (tổng trừ frozen).
     */
    public function getAvailableBalance(): float
    {
        return max(0, $this->getTotalBalance() - (float) $this->frozen_balance);
    }

    /**
     * Tính max giảm giá từ hoa hồng cho 1 đơn.
     * Rule: Chỉ được dùng 10% tổng số hoa hồng tích lũy.
     */
    public function getMaxCommissionDiscount(): float
    {
        return round((float) $this->commission_balance * 0.10, 2);
    }

    /**
     * Tính tổng giảm giá tối đa khả dụng cho 1 đơn hàng.
     */
    public function getMaxOrderDiscount(float $orderSubtotal): float
    {
        $fromDeposit = (float) $this->deposit_balance;
        $fromCommission = $this->getMaxCommissionDiscount();
        $maxDiscount = $fromDeposit + $fromCommission;

        return min($maxDiscount, $orderSubtotal);
    }

    /**
     * Kiểm tra ví có active không.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Kiểm tra có đủ tiền trong deposit_balance không.
     */
    public function hasDepositFunds(float $amount): bool
    {
        return (float) $this->deposit_balance >= $amount;
    }

    // ─── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id', 'wallet_id');
    }
}
