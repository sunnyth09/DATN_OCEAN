<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WalletController — API ví cá nhân cho user.
 *
 * Tất cả routes qua middleware 'auth:api' (đặt ở routes/api.php).
 *
 * Endpoints:
 *   GET  /api/wallet                   → Số dư + thống kê tổng hợp
 *   GET  /api/wallet/history           → Lịch sử giao dịch (paginated)
 *   GET  /api/wallet/preview-discount  → Preview giảm giá cho checkout
 */
class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * GET /api/wallet
     * Tổng quan ví: số dư, thống kê, giao dịch gần đây.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $summary = $this->walletService->getSummary($user->user_id);

        return response()->json([
            'status' => 'success',
            'data'   => $summary,
        ]);
    }

    /**
     * GET /api/wallet/history?type=commission&balance_type=deposit&per_page=20
     * Lịch sử giao dịch ví (phân trang, filter).
     */
    public function history(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $perPage     = min((int) ($request->per_page ?? 20), 100);
        $type        = $request->type;
        $balanceType = $request->balance_type;

        $history = $this->walletService->getHistory(
            $user->user_id,
            $perPage,
            $type,
            $balanceType
        );

        return response()->json([
            'status' => 'success',
            'data'   => $history,
        ]);
    }

    /**
     * GET /api/wallet/preview-discount?subtotal=800000
     * Preview giảm giá ví cho checkout page.
     */
    public function previewDiscount(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'subtotal' => 'required|numeric|min:0',
        ]);

        $preview = $this->walletService->previewDiscount(
            $user->user_id,
            (float) $request->subtotal
        );

        return response()->json([
            'status' => 'success',
            'data'   => $preview,
        ]);
    }

    /**
     * POST /api/wallet/withdraw
     * Rút tiền từ deposit_balance. Trừ ngay, phí 1,000₫/lần.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'amount'              => 'required|integer|min:10000|max:50000000',
            'bank_name'           => 'required|string|max:100',
            'bank_account_name'   => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:50|regex:/^[0-9\s-]+$/',
        ], [
            'amount.required'                  => 'Vui lòng nhập số tiền rút.',
            'amount.integer'                   => 'Số tiền rút phải là số nguyên.',
            'amount.min'                       => 'Số tiền rút tối thiểu 10,000₫.',
            'amount.max'                       => 'Số tiền rút tối đa 50,000,000₫.',
            'bank_name.required'               => 'Vui lòng nhập tên ngân hàng.',
            'bank_account_name.required'       => 'Vui lòng nhập tên chủ tài khoản.',
            'bank_account_number.required'     => 'Vui lòng nhập số tài khoản.',
            'bank_account_number.regex'        => 'Số tài khoản chỉ được chứa số, khoảng trắng hoặc dấu gạch ngang.',
        ]);

        $bankInfo = [
            'bank_name'           => preg_replace('/\s+/', ' ', trim($validated['bank_name'])),
            'bank_account_name'   => preg_replace('/\s+/', ' ', trim($validated['bank_account_name'])),
            'bank_account_number' => preg_replace('/[\s-]+/', '', trim($validated['bank_account_number'])),
        ];

        try {
            $result = $this->walletService->withdraw(
                $user->user_id,
                (float) $validated['amount'],
                $bankInfo
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Yêu cầu rút tiền đã được xử lý. Số dư đã được trừ.',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/wallet/withdrawals
     * Lịch sử rút tiền.
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $withdrawals = $this->walletService->getWithdrawals(
            $user->user_id,
            min((int) ($request->per_page ?? 15), 50)
        );

        return response()->json([
            'status' => 'success',
            'data'   => $withdrawals,
        ]);
    }
}
