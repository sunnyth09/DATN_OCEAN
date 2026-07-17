<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WalletService — Trung tâm logic xử lý ví cá nhân.
 *
 * Nguyên tắc:
 * - Pessimistic locking (lockForUpdate) trước mọi thay đổi balance
 * - Double-entry: ghi balance_before + balance_after mỗi giao dịch
 * - Idempotency: transaction_code unique chống duplicate
 * - Atomic: tất cả wrap trong DB::transaction()
 */
class WalletService
{
    /**
     * Tỷ lệ tối đa được dùng từ hoa hồng mỗi đơn.
     * 10% = 0.10
     */
    private const COMMISSION_DISCOUNT_RATE = 0.10;

    // ═══════════════════════════════════════════════════════════════
    // WALLET LIFECYCLE
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lấy hoặc tạo ví cho user.
     */
    public function getOrCreateWallet(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            [
                'deposit_balance'    => 0,
                'commission_balance' => 0,
                'frozen_balance'     => 0,
                'total_deposited'    => 0,
                'total_commission'   => 0,
                'total_used'         => 0,
                'status'             => 'active',
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // NẠP TIỀN (Credit)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Nạp tiền vào ví.
     *
     * - type = 'commission' → commission_balance
     * - type = deposit/refund/loyalty_convert/promo_credit → deposit_balance
     *
     * @param int    $userId
     * @param float  $amount  Số tiền (phải > 0)
     * @param string $type    deposit|commission|refund|loyalty_convert|promo_credit|adjustment
     * @param array  $opts    [reference_type, reference_id, description, metadata]
     * @return WalletTransaction
     */
    public function credit(int $userId, float $amount, string $type, array $opts = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền nạp phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $amount, $type, $opts) {
            // Lấy hoặc tạo ví, rồi lock
            $this->getOrCreateWallet($userId);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (!$wallet->isActive()) {
                throw new \Exception('Ví đã bị đóng băng hoặc đóng.');
            }

            $balanceType = ($type === 'commission') ? 'commission' : 'deposit';

            if ($balanceType === 'commission') {
                $before = (float) $wallet->commission_balance;
                $wallet->commission_balance += $amount;
                $wallet->total_commission   += $amount;
                $after = (float) $wallet->commission_balance;
            } else {
                $before = (float) $wallet->deposit_balance;
                $wallet->deposit_balance += $amount;
                $wallet->total_deposited += $amount;
                $after = (float) $wallet->deposit_balance;
            }

            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id'        => $wallet->wallet_id,
                'transaction_code' => $this->generateTransactionCode(),
                'type'             => $type,
                'balance_type'     => $balanceType,
                'direction'        => 'credit',
                'amount'           => $amount,
                'balance_before'   => $before,
                'balance_after'    => $after,
                'reference_type'   => $opts['reference_type'] ?? null,
                'reference_id'     => $opts['reference_id'] ?? null,
                'description'      => $opts['description'] ?? null,
                'status'           => 'completed',
                'metadata'         => $opts['metadata'] ?? null,
            ]);

            Log::info('Wallet credit', [
                'user_id'  => $userId,
                'type'     => $type,
                'amount'   => $amount,
                'tx_code'  => $transaction->transaction_code,
            ]);

            return $transaction;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // GIẢM GIÁ ĐƠN HÀNG (Debit)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Áp dụng ví giảm giá cho đơn hàng.
     *
     * Ưu tiên trừ deposit_balance trước, sau đó commission_balance (max 10%).
     *
     * @param int   $userId
     * @param float $requestedAmount  Tổng số tiền user muốn dùng từ ví
     * @param int   $orderId
     * @return array{deposit_used: float, commission_used: float, total_discount: float, transactions: WalletTransaction[]}
     */
    public function applyOrderDiscount(int $userId, float $requestedAmount, int $orderId): array
    {
        if ($requestedAmount <= 0) {
            throw new \InvalidArgumentException('Số tiền giảm giá phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $requestedAmount, $orderId) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if (!$wallet->isActive()) {
                throw new \Exception('Ví đã bị đóng băng hoặc đóng.');
            }

            // Tính giới hạn
            $maxFromDeposit    = (float) $wallet->deposit_balance;
            $maxFromCommission = $wallet->getMaxCommissionDiscount(); // 10% hoa hồng

            // Ưu tiên trừ deposit trước
            $depositUsed    = min($requestedAmount, $maxFromDeposit);
            $remaining      = $requestedAmount - $depositUsed;
            $commissionUsed = min($remaining, $maxFromCommission);
            $totalDiscount  = $depositUsed + $commissionUsed;

            if ($totalDiscount <= 0) {
                throw new \Exception('Không đủ số dư ví để giảm giá.');
            }

            $transactions = [];

            // Trừ deposit_balance
            if ($depositUsed > 0) {
                $before = (float) $wallet->deposit_balance;
                $wallet->deposit_balance -= $depositUsed;
                $wallet->total_used      += $depositUsed;

                $transactions[] = WalletTransaction::create([
                    'wallet_id'        => $wallet->wallet_id,
                    'transaction_code' => $this->generateTransactionCode(),
                    'type'             => 'order_discount',
                    'balance_type'     => 'deposit',
                    'direction'        => 'debit',
                    'amount'           => $depositUsed,
                    'balance_before'   => $before,
                    'balance_after'    => (float) $wallet->deposit_balance,
                    'reference_type'   => Order::class,
                    'reference_id'     => $orderId,
                    'description'      => 'Giảm giá đơn hàng (từ số dư nạp)',
                    'status'           => 'completed',
                ]);
            }

            // Trừ commission_balance (max 10%)
            if ($commissionUsed > 0) {
                $before = (float) $wallet->commission_balance;
                $wallet->commission_balance -= $commissionUsed;
                $wallet->total_used         += $commissionUsed;

                $transactions[] = WalletTransaction::create([
                    'wallet_id'        => $wallet->wallet_id,
                    'transaction_code' => $this->generateTransactionCode(),
                    'type'             => 'order_discount',
                    'balance_type'     => 'commission',
                    'direction'        => 'debit',
                    'amount'           => $commissionUsed,
                    'balance_before'   => $before,
                    'balance_after'    => (float) $wallet->commission_balance,
                    'reference_type'   => Order::class,
                    'reference_id'     => $orderId,
                    'description'      => 'Giảm giá đơn hàng (từ hoa hồng, max 10%)',
                    'status'           => 'completed',
                ]);
            }

            $wallet->save();

            Log::info('Wallet order discount applied', [
                'user_id'         => $userId,
                'order_id'        => $orderId,
                'deposit_used'    => $depositUsed,
                'commission_used' => $commissionUsed,
                'total_discount'  => $totalDiscount,
            ]);

            return [
                'deposit_used'    => $depositUsed,
                'commission_used' => $commissionUsed,
                'total_discount'  => $totalDiscount,
                'transactions'    => $transactions,
            ];
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // HOÀN TRẢ KHI CANCEL ORDER
    // ═══════════════════════════════════════════════════════════════

    /**
     * Hoàn tiền ví khi cancel đơn hàng.
     * Hoàn lại đúng loại balance đã dùng.
     */
    public function reverseOrderDiscount(int $userId, float $depositAmount, float $commissionAmount, int $orderId): void
    {
        if ($depositAmount > 0) {
            $this->credit($userId, $depositAmount, 'refund', [
                'reference_type' => Order::class,
                'reference_id'   => $orderId,
                'description'    => "Hoàn tiền ví (nạp) do hủy đơn #{$orderId}",
            ]);
        }

        if ($commissionAmount > 0) {
            // Hoàn lại vào commission_balance
            DB::transaction(function () use ($userId, $commissionAmount, $orderId) {
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

                $before = (float) $wallet->commission_balance;
                $wallet->commission_balance += $commissionAmount;
                $wallet->total_used -= $commissionAmount;
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id'        => $wallet->wallet_id,
                    'transaction_code' => $this->generateTransactionCode(),
                    'type'             => 'refund',
                    'balance_type'     => 'commission',
                    'direction'        => 'credit',
                    'amount'           => $commissionAmount,
                    'balance_before'   => $before,
                    'balance_after'    => (float) $wallet->commission_balance,
                    'reference_type'   => Order::class,
                    'reference_id'     => $orderId,
                    'description'      => "Hoàn hoa hồng do hủy đơn #{$orderId}",
                    'status'           => 'completed',
                ]);
            });

            Log::info('Wallet commission reversed', [
                'user_id'  => $userId,
                'order_id' => $orderId,
                'amount'   => $commissionAmount,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // PREVIEW & QUERY
    // ═══════════════════════════════════════════════════════════════

    /**
     * Preview giảm giá ví cho 1 đơn hàng.
     */
    public function previewDiscount(int $userId, float $orderSubtotal): array
    {
        $wallet = $this->getOrCreateWallet($userId);

        $maxDeposit    = (float) $wallet->deposit_balance;
        $maxCommission = $wallet->getMaxCommissionDiscount();
        $maxTotal      = min($maxDeposit + $maxCommission, $orderSubtotal);

        // Mô phỏng logic applyOrderDiscount: deposit trước, commission sau
        $depositUsed    = min($orderSubtotal, $maxDeposit);
        $remaining      = $orderSubtotal - $depositUsed;
        $commissionUsed = min($remaining, $maxCommission);

        return [
            'deposit_balance'     => (float) $wallet->deposit_balance,
            'commission_balance'  => (float) $wallet->commission_balance,
            'max_from_deposit'    => $maxDeposit,
            'max_from_commission' => $maxCommission,
            'max_total_discount'  => $maxTotal,
            'total_available'     => $maxTotal,        // Frontend-friendly alias
            'deposit_used'        => $depositUsed,     // Preview: sẽ dùng bao nhiêu từ deposit
            'commission_used'     => $commissionUsed,  // Preview: sẽ dùng bao nhiêu từ commission
            'remaining_payment'   => max(0, $orderSubtotal - $maxTotal),
        ];
    }

    /**
     * Lấy số dư ví.
     */
    public function getBalance(int $userId): array
    {
        $wallet = $this->getOrCreateWallet($userId);

        return [
            'deposit_balance'        => (float) $wallet->deposit_balance,
            'commission_balance'     => (float) $wallet->commission_balance,
            'total_balance'          => $wallet->getTotalBalance(),
            'frozen_balance'         => (float) $wallet->frozen_balance,
            'available_balance'      => $wallet->getAvailableBalance(),
            'max_commission_per_order' => $wallet->getMaxCommissionDiscount(),
            'total_deposited'        => (float) $wallet->total_deposited,
            'total_commission'       => (float) $wallet->total_commission,
            'total_used'             => (float) $wallet->total_used,
            'status'                 => $wallet->status,
        ];
    }

    /**
     * Lịch sử giao dịch (phân trang).
     */
    public function getHistory(int $userId, int $perPage = 20, ?string $type = null, ?string $balanceType = null): LengthAwarePaginator
    {
        $wallet = $this->getOrCreateWallet($userId);

        $query = WalletTransaction::where('wallet_id', $wallet->wallet_id)
            ->completed()
            ->orderByDesc('created_at');

        if ($type) {
            $query->byType($type);
        }

        if ($balanceType) {
            $query->byBalanceType($balanceType);
        }

        $history = $query->paginate($perPage);

        // Transform cho frontend
        $history->getCollection()->transform(fn (WalletTransaction $tx) => [
            'id'               => $tx->transaction_id,
            'transaction_code' => $tx->transaction_code,
            'type'             => $tx->type,
            'type_label'       => $tx->typeLabel(),
            'type_icon'        => $tx->typeIcon(),
            'balance_type'     => $tx->balance_type,
            'direction'        => $tx->direction,
            'sign'             => $tx->sign,
            'amount'           => (float) $tx->amount,
            'balance_before'   => (float) $tx->balance_before,
            'balance_after'    => (float) $tx->balance_after,
            'description'      => $tx->description,
            'created_at'       => $tx->created_at?->toISOString(),
        ]);

        return $history;
    }

    /**
     * Thống kê tổng hợp ví.
     */
    public function getSummary(int $userId): array
    {
        $balance = $this->getBalance($userId);
        $wallet  = Wallet::where('user_id', $userId)->first();

        if (!$wallet) {
            return array_merge($balance, [
                'this_month_earned' => 0,
                'this_month_used'   => 0,
                'recent_transactions' => [],
            ]);
        }

        $startOfMonth = now()->startOfMonth();

        $thisMonthEarned = WalletTransaction::where('wallet_id', $wallet->wallet_id)
            ->completed()
            ->credits()
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $thisMonthUsed = WalletTransaction::where('wallet_id', $wallet->wallet_id)
            ->completed()
            ->debits()
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $recentTransactions = WalletTransaction::where('wallet_id', $wallet->wallet_id)
            ->completed()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id'         => $tx->transaction_id,
                'type'       => $tx->type,
                'type_label' => $tx->typeLabel(),
                'type_icon'  => $tx->typeIcon(),
                'sign'       => $tx->sign,
                'amount'     => (float) $tx->amount,
                'created_at' => $tx->created_at?->toISOString(),
            ]);

        return array_merge($balance, [
            'this_month_earned'    => (float) $thisMonthEarned,
            'this_month_used'      => (float) $thisMonthUsed,
            'recent_transactions'  => $recentTransactions,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ADMIN
    // ═══════════════════════════════════════════════════════════════

    /**
     * Admin điều chỉnh số dư ví (deposit_balance).
     */
    public function adminAdjust(int $userId, float $delta, string $description, int $adminId): WalletTransaction
    {
        if ($delta == 0) {
            throw new \InvalidArgumentException('Số tiền điều chỉnh phải khác 0');
        }

        return DB::transaction(function () use ($userId, $delta, $description, $adminId) {
            $this->getOrCreateWallet($userId);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            $before = (float) $wallet->deposit_balance;
            $wallet->deposit_balance += $delta;

            if ((float) $wallet->deposit_balance < 0) {
                throw new \Exception('Không thể trừ quá số dư hiện tại.');
            }

            if ($delta > 0) {
                $wallet->total_deposited += $delta;
            }

            $wallet->save();

            return WalletTransaction::create([
                'wallet_id'        => $wallet->wallet_id,
                'transaction_code' => $this->generateTransactionCode(),
                'type'             => 'adjustment',
                'balance_type'     => 'deposit',
                'direction'        => $delta > 0 ? 'credit' : 'debit',
                'amount'           => abs($delta),
                'balance_before'   => $before,
                'balance_after'    => (float) $wallet->deposit_balance,
                'description'      => $description,
                'status'           => 'completed',
                'metadata'         => ['admin_id' => $adminId],
            ]);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // RÚT TIỀN (Withdrawal)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Phí rút tiền cố định.
     */
    private const WITHDRAWAL_FEE = 1000;

    /**
     * Số tiền rút tối thiểu.
     */
    private const WITHDRAWAL_MIN = 10000;

    /**
     * Rút tiền từ deposit_balance.
     *
     * - Trừ ngay (amount + phí 1,000₫) từ deposit_balance
     * - Không cần admin duyệt
     * - Ghi log WalletTransaction + wallet_withdrawals
     *
     * @return array{withdrawal_id: int, withdrawal_code: string, amount: float, fee: float, total_deducted: float}
     */
    public function withdraw(int $userId, float $amount, array $bankInfo): array
    {
        if ($amount < self::WITHDRAWAL_MIN) {
            throw new \InvalidArgumentException('Số tiền rút tối thiểu ' . number_format(self::WITHDRAWAL_MIN) . '₫');
        }

        $fee           = self::WITHDRAWAL_FEE;
        $totalDeducted = $amount + $fee;

        return DB::transaction(function () use ($userId, $amount, $fee, $totalDeducted, $bankInfo) {
            $this->getOrCreateWallet($userId);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (!$wallet->isActive()) {
                throw new \Exception('Ví đã bị đóng băng hoặc đóng.');
            }

            if ((float) $wallet->deposit_balance < $totalDeducted) {
                throw new \Exception('Số dư nạp không đủ. Cần tối thiểu ' . number_format($totalDeducted) . '₫ (bao gồm phí ' . number_format($fee) . '₫).');
            }

            // Trừ deposit_balance
            $before = (float) $wallet->deposit_balance;
            $wallet->deposit_balance -= $totalDeducted;
            $wallet->total_used      += $totalDeducted;
            $wallet->save();

            $withdrawalCode = 'WWD' . strtoupper(Str::random(10));

            // Ghi WalletTransaction
            WalletTransaction::create([
                'wallet_id'        => $wallet->wallet_id,
                'transaction_code' => $this->generateTransactionCode(),
                'type'             => 'withdrawal',
                'balance_type'     => 'deposit',
                'direction'        => 'debit',
                'amount'           => $totalDeducted,
                'balance_before'   => $before,
                'balance_after'    => (float) $wallet->deposit_balance,
                'description'      => "Rút {$amount}₫ (phí {$fee}₫) → {$bankInfo['bank_name']} - {$bankInfo['bank_account_number']}",
                'status'           => 'completed',
                'metadata'         => [
                    'withdrawal_code' => $withdrawalCode,
                    'amount'          => $amount,
                    'fee'             => $fee,
                    'bank_name'       => $bankInfo['bank_name'],
                    'bank_account'    => $bankInfo['bank_account_number'],
                ],
            ]);

            // Ghi wallet_withdrawals
            $withdrawalId = DB::table('wallet_withdrawals')->insertGetId([
                'user_id'             => $userId,
                'withdrawal_code'     => $withdrawalCode,
                'amount'              => $amount,
                'fee'                 => $fee,
                'total_deducted'      => $totalDeducted,
                'bank_name'           => $bankInfo['bank_name'],
                'bank_account_name'   => $bankInfo['bank_account_name'],
                'bank_account_number' => $bankInfo['bank_account_number'],
                'status'              => 'processing',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            Log::info('Wallet withdrawal created', [
                'user_id'         => $userId,
                'withdrawal_code' => $withdrawalCode,
                'amount'          => $amount,
                'fee'             => $fee,
                'total_deducted'  => $totalDeducted,
            ]);

            return [
                'withdrawal_id'   => $withdrawalId,
                'withdrawal_code' => $withdrawalCode,
                'amount'          => $amount,
                'fee'             => $fee,
                'total_deducted'  => $totalDeducted,
                'new_balance'     => (float) $wallet->deposit_balance,
            ];
        });
    }

    /**
     * Lấy lịch sử rút tiền của user.
     */
    public function getWithdrawals(int $userId, int $perPage = 15)
    {
        return DB::table('wallet_withdrawals')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Sinh transaction code unique.
     */
    private function generateTransactionCode(): string
    {
        return 'WTX-' . strtoupper(Str::random(12));
    }
}
