<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\AffiliateWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Lấy ví điện tử của user, tự động tạo nếu chưa có
     */
    public function getWallet(int $userId): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $userId],
            [
                'balance' => 0.00,
                'affiliate_earnings' => 0.00,
                'withdrawn_amount' => 0.00,
            ]
        );
    }

    /**
     * Nạp tiền vào ví (Ví dụ: nhận hoa hồng Affiliate, admin cộng tiền...)
     */
    public function deposit(
        int $userId,
        float $amount,
        string $type,
        string $description,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền nạp phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $amount, $type, $description, $referenceId, $referenceType) {
            $wallet = $this->getWallet($userId);

            $wallet->balance += $amount;
            if ($type === 'commission') {
                $wallet->affiliate_earnings += $amount;
            }
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => $type,
                'status' => 'completed',
                'description' => $description,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
            ]);
        });
    }

    /**
     * Thanh toán đơn hàng bằng số dư ví
     */
    public function spend(
        int $userId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền thanh toán phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $amount, $description, $referenceId, $referenceType) {
            $wallet = $this->getWallet($userId);

            if ($wallet->balance < $amount) {
                throw new \Exception('Số dư ví không đủ để thực hiện thanh toán!');
            }

            $wallet->balance -= $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => -$amount,
                'type' => 'spend',
                'status' => 'completed',
                'description' => $description,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
            ]);
        });
    }

    /**
     * Hoàn tiền đã chi tiêu (Ví dụ: khi đơn hàng thanh toán bằng ví bị huỷ)
     */
    public function refund(
        int $userId,
        float $amount,
        string $description,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền hoàn phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $amount, $description, $referenceId, $referenceType) {
            $wallet = $this->getWallet($userId);

            $wallet->balance += $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'refund',
                'status' => 'completed',
                'description' => $description,
                'reference_id' => $referenceId,
                'reference_type' => $referenceType,
            ]);
        });
    }

    /**
     * Gửi yêu cầu rút tiền
     */
    public function requestWithdrawal(
        int $userId,
        float $amount,
        string $bankName,
        string $bankAccountName,
        string $bankAccountNumber,
        string $method = 'bank'
    ): AffiliateWithdrawal {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Số tiền rút phải lớn hơn 0');
        }

        return DB::transaction(function () use ($userId, $amount, $bankName, $bankAccountName, $bankAccountNumber, $method) {
            $wallet = $this->getWallet($userId);

            if ($wallet->balance < $amount) {
                throw new \Exception('Số dư ví không đủ để yêu cầu rút tiền!');
            }

            // Trừ số dư luôn để chống double spend
            $wallet->balance -= $amount;
            $wallet->save();

            $withdrawal = AffiliateWithdrawal::create([
                'user_id' => $userId,
                'amount' => $amount,
                'withdrawal_method' => $method,
                'bank_name' => $bankName,
                'bank_account_name' => $bankAccountName,
                'bank_account_number' => $bankAccountNumber,
                'status' => 'pending',
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => -$amount,
                'type' => 'withdraw',
                'status' => 'pending',
                'description' => "Yêu cầu rút tiền (" . ($method === 'vnpay' ? 'Ví VNPay' : $bankName) . ")",
                'reference_id' => $withdrawal->id,
                'reference_type' => AffiliateWithdrawal::class,
            ]);

            return $withdrawal;
        });
    }

    /**
     * Admin duyệt yêu cầu rút tiền
     */
    public function approveWithdrawal(int $withdrawalId): void
    {
        DB::transaction(function () use ($withdrawalId) {
            $withdrawal = AffiliateWithdrawal::findOrFail($withdrawalId);
            if ($withdrawal->status !== 'pending') {
                throw new \Exception('Yêu cầu rút tiền đã được xử lý từ trước!');
            }

            $withdrawal->status = 'approved';
            $withdrawal->save();

            // Vẫn giữ giao dịch rút tiền trạng thái pending cho tới khi Paid thực tế
        });
    }

    /**
     * Admin từ chối yêu cầu rút tiền -> hoàn lại tiền vào ví
     */
    public function rejectWithdrawal(int $withdrawalId, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawalId, $note) {
            $withdrawal = AffiliateWithdrawal::findOrFail($withdrawalId);
            if ($withdrawal->status !== 'pending' && $withdrawal->status !== 'approved') {
                throw new \Exception('Chỉ có thể từ chối yêu cầu đang chờ hoặc đã duyệt!');
            }

            $oldStatus = $withdrawal->status;
            $withdrawal->status = 'rejected';
            if ($note !== null) {
                $withdrawal->note = $note;
            }
            $withdrawal->save();

            $wallet = $this->getWallet($withdrawal->user_id);

            // Hoàn lại tiền
            $wallet->balance += $withdrawal->amount;
            $wallet->save();

            // Cập nhật trạng thái giao dịch cũ từ pending/completed thành cancelled
            $oldTx = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'withdraw')
                ->where('reference_id', $withdrawalId)
                ->where('reference_type', AffiliateWithdrawal::class)
                ->first();

            if ($oldTx) {
                $oldTx->status = 'cancelled';
                $oldTx->save();
            }

            // Tạo giao dịch hoàn tiền rút
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $withdrawal->amount,
                'type' => 'refund',
                'status' => 'completed',
                'description' => "Hoàn tiền yêu cầu rút #" . $withdrawalId . " bị từ chối",
                'reference_id' => $withdrawalId,
                'reference_type' => AffiliateWithdrawal::class,
            ]);
        });
    }

    /**
     * Admin xác nhận đã chuyển khoản thành công
     */
    public function payWithdrawal(int $withdrawalId): void
    {
        DB::transaction(function () use ($withdrawalId) {
            $withdrawal = AffiliateWithdrawal::findOrFail($withdrawalId);
            if ($withdrawal->status !== 'approved') {
                throw new \Exception('Chỉ có thể đánh dấu thanh toán cho yêu cầu đã duyệt!');
            }

            $withdrawal->status = 'paid';
            $withdrawal->save();

            $wallet = $this->getWallet($withdrawal->user_id);
            $wallet->withdrawn_amount += $withdrawal->amount;
            $wallet->save();

            // Cập nhật giao dịch ví thành completed
            $tx = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'withdraw')
                ->where('reference_id', $withdrawalId)
                ->where('reference_type', AffiliateWithdrawal::class)
                ->first();

            if ($tx) {
                $tx->status = 'completed';
                $tx->save();
            }
        });
    }
}
