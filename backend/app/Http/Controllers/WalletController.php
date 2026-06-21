<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use App\Models\WalletTransaction;
use App\Models\AffiliateWithdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * GET /api/wallet/summary
     * Lấy tóm tắt ví điện tử của user
     */
    public function summary(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $this->walletService->getWallet($user->user_id);

        $pendingWithdrawals = (float) AffiliateWithdrawal::where('user_id', $user->user_id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'status' => 'success',
            'data' => [
                'balance' => $wallet->balance,
                'affiliate_earnings' => $wallet->affiliate_earnings,
                'withdrawn_amount' => $wallet->withdrawn_amount,
                'pending_withdrawals' => $pendingWithdrawals,
                'is_affiliate' => $user->is_affiliate,
            ]
        ]);
    }

    /**
     * GET /api/wallet/history
     * Lấy lịch sử giao dịch ví (phân trang)
     */
    public function history(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $this->walletService->getWallet($user->user_id);
        $perPage = min((int) ($request->get('per_page', 10)), 100);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }

    /**
     * POST /api/wallet/withdraw
     * Yêu cầu rút tiền từ ví về ngân hàng hoặc VNPay
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $minAmount = config('affiliate.min_withdraw_amount', 100000);

        $request->validate([
            'amount' => "required|numeric|min:{$minAmount}",
            'withdrawal_method' => 'required|in:bank,vnpay',
            'bank_name' => 'required_if:withdrawal_method,bank|nullable|string|max:100',
            'bank_account_name' => 'required_if:withdrawal_method,bank|nullable|string|max:255',
            'bank_account_number' => 'required_if:withdrawal_method,bank|nullable|string|max:50',
        ], [
            'amount.required' => 'Vui lòng nhập số tiền cần rút.',
            'amount.min' => "Số tiền rút tối thiểu là " . number_format($minAmount, 0, ',', '.') . "đ.",
            'withdrawal_method.required' => 'Vui lòng chọn phương thức rút tiền.',
            'bank_name.required_if' => 'Vui lòng điền tên ngân hàng.',
            'bank_account_name.required_if' => 'Vui lòng điền tên chủ tài khoản.',
            'bank_account_number.required_if' => 'Vui lòng điền số tài khoản.',
        ]);

        try {
            $method = $request->withdrawal_method;
            if ($method === 'vnpay') {
                $bankName = 'VNPay Wallet';
                $bankAccountName = $user->full_name;
                $bankAccountNumber = $user->phone ?? '';
            } else {
                $bankName = $request->bank_name;
                $bankAccountName = $request->bank_account_name;
                $bankAccountNumber = $request->bank_account_number;
            }

            // Kiểm tra xem có yêu cầu pending khác không
            $hasPending = AffiliateWithdrawal::where('user_id', $user->user_id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn đang có yêu cầu rút tiền chưa được xử lý. Vui lòng chờ duyệt!'
                ], 422);
            }

            $withdrawal = $this->walletService->requestWithdrawal(
                $user->user_id,
                (float) $request->amount,
                $bankName,
                $bankAccountName,
                $bankAccountNumber,
                $method
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Gửi yêu cầu rút tiền thành công! Vui lòng chờ duyệt.',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
