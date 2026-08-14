<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MoMoService;
use App\Services\VNPayService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WalletDepositController — Nạp tiền trực tiếp vào ví.
 *
 * Hỗ trợ: VNPay, MoMo, Bank Transfer (SePay).
 *
 * Luồng:
 * 1. User POST /wallet/deposit/init {amount, method}
 * 2. Server tạo deposit_code (WDP-xxx), tạo payment URL
 * 3. User redirect → gateway thanh toán
 * 4. Webhook/IPN callback → SepayController/VNPayController detect WDP prefix
 *    → WalletService::credit(deposit)
 */
class WalletDepositController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * POST /api/wallet/deposit/init
     *
     * Khởi tạo giao dịch nạp tiền ví.
     *
     * Body: { amount: int, method: "vnpay"|"momo"|"bank_transfer" }
     */
    public function initDeposit(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'amount' => 'required|numeric|min:10000|max:50000000',
            'method' => 'required|string|in:vnpay,momo,bank_transfer',
        ], [
            'amount.required' => 'Vui lòng nhập số tiền nạp.',
            'amount.min' => 'Số tiền nạp tối thiểu 10,000₫.',
            'amount.max' => 'Số tiền nạp tối đa 50,000,000₫.',
            'method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'method.in' => 'Phương thức thanh toán không hợp lệ.',
        ]);

        $amount = (int) $request->amount;
        $method = $request->method;

        // Tạo deposit code unique (WDP prefix để SePay/VNPay nhận diện)
        $depositCode = 'WDP'.strtoupper(Str::random(10));

        try {
            if ($method === 'vnpay') {
                return $this->handleVNPayDeposit($user, $amount, $depositCode, $request);
            }

            if ($method === 'momo') {
                return $this->handleMoMoDeposit($user, $amount, $depositCode);
            }

            // bank_transfer → QR code
            return $this->handleBankDeposit($user, $amount, $depositCode);

        } catch (\Exception $e) {
            Log::error('Wallet deposit init failed', [
                'user_id' => $user->user_id,
                'amount' => $amount,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Không thể khởi tạo nạp tiền. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * VNPay: Tạo "pseudo-order" object để tái sử dụng VNPayService::createPaymentUrl()
     */
    private function handleVNPayDeposit($user, int $amount, string $depositCode, Request $request): JsonResponse
    {
        // Tạo pseudo-order object (VNPayService chỉ cần order_code + grand_total)
        $pseudoOrder = new \stdClass;
        $pseudoOrder->order_code = $depositCode;
        $pseudoOrder->grand_total = $amount;

        $vnpayUrl = VNPayService::createPaymentUrl($pseudoOrder, $request->ip());

        // Lưu pending deposit vào cache/DB để track (30 phút TTL)
        $this->storePendingDeposit($user->user_id, $depositCode, $amount, 'vnpay');

        return response()->json([
            'status' => 'success',
            'message' => 'Đang chuyển đến cổng thanh toán VNPay...',
            'data' => [
                'deposit_code' => $depositCode,
                'amount' => $amount,
                'payment_method' => 'vnpay',
                'redirect_url' => $vnpayUrl,
            ],
        ]);
    }

    /**
     * MoMo: Tạo pseudo-order cho MoMoService
     */
    private function handleMoMoDeposit($user, int $amount, string $depositCode): JsonResponse
    {
        $pseudoOrder = new \stdClass;
        $pseudoOrder->order_code = $depositCode;
        $pseudoOrder->grand_total = $amount;
        $pseudoOrder->order_id = 0;

        $momoUrl = MoMoService::createPaymentUrl($pseudoOrder);

        $this->storePendingDeposit($user->user_id, $depositCode, $amount, 'momo');

        return response()->json([
            'status' => 'success',
            'message' => 'Đang chuyển đến cổng thanh toán MoMo...',
            'data' => [
                'deposit_code' => $depositCode,
                'amount' => $amount,
                'payment_method' => 'momo',
                'redirect_url' => $momoUrl,
            ],
        ]);
    }

    /**
     * Bank Transfer: Tạo VietQR code
     */
    private function handleBankDeposit($user, int $amount, string $depositCode): JsonResponse
    {
        $bankBin = config('services.bank.bin');
        $bankAccount = config('services.bank.account_number');
        $accountName = config('services.bank.account_name');

        $qrUrl = "https://qr.sepay.vn/img?acc={$bankAccount}&bank={$bankBin}&amount={$amount}&des=".urlencode($depositCode);

        $this->storePendingDeposit($user->user_id, $depositCode, $amount, 'bank_transfer');

        return response()->json([
            'status' => 'success',
            'message' => 'Vui lòng chuyển khoản để nạp tiền vào ví.',
            'data' => [
                'deposit_code' => $depositCode,
                'amount' => $amount,
                'payment_method' => 'bank_transfer',
                'banking_info' => [
                    'bank_bin' => $bankBin,
                    'account_number' => $bankAccount,
                    'account_name' => $accountName,
                    'amount' => $amount,
                    'content' => $depositCode,
                    'qr_url' => $qrUrl,
                ],
            ],
        ]);
    }

    /**
     * Lưu pending deposit vào bảng wallet_deposits (hoặc cache).
     * Dùng DB để idempotency check khi webhook callback.
     */
    private function storePendingDeposit(int $userId, string $depositCode, int $amount, string $method): void
    {
        DB::table('wallet_deposits')->insert([
            'user_id' => $userId,
            'deposit_code' => $depositCode,
            'amount' => $amount,
            'method' => $method,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
