<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentProcessingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SepayController extends Controller
{
    protected PaymentProcessingService $paymentService;

    protected WalletService $walletService;

    public function __construct(PaymentProcessingService $paymentService, WalletService $walletService)
    {
        $this->paymentService = $paymentService;
        $this->walletService = $walletService;
    }

    public function handleWebhook(Request $request)
    {
        // 1. Authenticate Request (bắt buộc — hard-fail nếu chưa cấu hình key)
        $expectedKey = config('services.sepay.api_key');

        if (empty($expectedKey)) {
            Log::critical('SePay Webhook: SEPAY_API_KEY chưa được cấu hình — từ chối mọi webhook');

            return response()->json(['status' => 'error', 'message' => 'Webhook not configured'], 500);
        }

        $authHeader = $request->header('Authorization');
        $apiKey = $authHeader ? str_replace('Apikey ', '', $authHeader) : '';

        if (! hash_equals($expectedKey, $apiKey)) {
            Log::warning('SePay Webhook: Unauthorized webhook call', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // 2. Extract transaction fields from SePay payload
        // FIX #3: dùng filter() để bỏ qua cả null lẫn chuỗi rỗng "" — PHP ?? chỉ skip null.
        $transferContent = collect([
            $payload['code']        ?? null,
            $payload['content']     ?? null,
            $payload['description'] ?? null,
        ])->filter(fn ($v) => ! empty($v))->first() ?? '';

        $transferAmount = (float) ($payload['transferAmount'] ?? 0);  // Transferred amount
        $transactionId  = (string) ($payload['id'] ?? '');            // Transaction code

        Log::info('SePay Webhook: Processing bank transfer', [
            'transaction_id' => $transactionId,
            'content' => $transferContent,
            'amount' => $transferAmount,
        ]);

        // 3. Extract code from transfer content (WDP = wallet deposit, ORD/DH = order)
        $code = $this->extractPaymentCode($transferContent);

        if (! $code) {
            Log::warning('SePay Webhook: Unable to extract payment code from transfer content', [
                'content' => $transferContent,
            ]);

            return response()->json(['status' => 'error', 'message' => 'Payment code not found in content'], 200);
        }

        // ── Wallet Deposit (WDP prefix) ──
        if (str_starts_with($code, 'WDP')) {
            return $this->handleWalletDeposit($code, $transferAmount, $transactionId, $payload);
        }

        // ── Order Payment (ORD/DH prefix) ──
        $orderCode = $code;

        // 4. Update order and create payment record in database transaction
        try {
            $order = null;
            $response = DB::transaction(function () use ($orderCode, $transferAmount, $transactionId, $payload, &$order) {
                $order = Order::where('order_code', $orderCode)->lockForUpdate()->first();

                if (! $order) {
                    Log::warning('SePay Webhook: Order not found', ['order_code' => $orderCode]);

                    return ['status' => 'error', 'message' => 'Order not found: '.$orderCode];
                }

                if ($order->payment_status === 'paid') {
                    return ['status' => 'success', 'message' => 'Order already paid']; // Idempotency
                }

                // Verify amount (allow small rounding difference e.g. < 10 VND)
                if (abs($transferAmount - $order->grand_total) > 10) {
                    Log::error('SePay Webhook: Amount mismatch', [
                        'order' => $order->order_code,
                        'expected' => $order->grand_total,
                        'received' => $transferAmount,
                    ]);

                    return ['status' => 'error', 'message' => 'Amount mismatch'];
                }

                // Update order status
                $order->update(['payment_status' => 'paid']);

                // Log the payment
                Payment::updateOrCreate(
                    [
                        'order_id' => $order->order_id,
                        'payment_method' => 'bank_transfer',
                    ],
                    [
                        'transaction_code' => $transactionId,
                        'amount' => $transferAmount,
                        'status' => 'success',
                        'paid_at' => now(),
                        'gateway_response' => $payload,
                    ]
                );

                return ['status' => 'success', 'message' => 'Payment processed successfully'];
            });

            // FIX #1 & #2: Side-effects chạy SAU khi transaction commit.
            // Nếu email/socket thất bại, payment vẫn được ghi nhận — không rollback.
            if ($response['status'] === 'success' && $order) {
                try {
                    // FIX #2: Load đầy đủ relations cho email xác nhận có danh sách sản phẩm
                    $order->loadMissing(['items', 'user']);
                    $this->paymentService->dispatchPostPaymentActions($order);
                    Log::info('SePay Webhook: Post-payment actions dispatched', [
                        'order_code' => $order->order_code,
                    ]);
                } catch (\Throwable $e) {
                    // Không throw — payment đã thành công, side-effects sẽ được
                    // bù bởi Scheduler polling job nếu cần.
                    Log::error('SePay Webhook: Post-payment side-effects failed (payment still recorded)', [
                        'order_code' => $order->order_code,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('SePay Webhook processing error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Xử lý nạp ví từ bank transfer (WDP prefix).
     */
    private function handleWalletDeposit(string $depositCode, float $amount, string $transactionId, array $payload)
    {
        try {
            $result = DB::transaction(function () use ($depositCode, $amount, $transactionId, $payload) {
                $deposit = DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->lockForUpdate()
                    ->first();

                if (! $deposit) {
                    Log::warning('SePay Wallet: Deposit code not found', ['code' => $depositCode]);

                    return ['status' => 'error', 'message' => 'Deposit code not found: '.$depositCode];
                }

                // Chặn cả trạng thái terminal 'completed' VÀ 'failed'. deposit_code là
                // duy nhất mỗi lần init (WDP + random, không tái dùng khi retry), nên một
                // deposit đã 'failed' không bao giờ được nạp lại hợp lệ → chặn để tránh
                // double-credit khi webhook failed đến trước rồi success đến sau cùng code.
                if (in_array($deposit->status, ['completed', 'failed'], true)) {
                    return ['status' => 'success', 'message' => 'Deposit already in terminal state']; // Idempotency
                }

                // Verify amount (cho phép chênh lệch nhỏ < 10 VND)
                if (abs($amount - (float) $deposit->amount) > 10) {
                    Log::error('SePay Wallet: Amount mismatch', [
                        'deposit_code' => $depositCode,
                        'expected' => $deposit->amount,
                        'received' => $amount,
                    ]);

                    return ['status' => 'error', 'message' => 'Amount mismatch'];
                }

                // Credit vào ví
                $this->walletService->credit(
                    userId: $deposit->user_id,
                    amount: (float) $deposit->amount,
                    type: 'deposit',
                    opts: [
                        'description' => 'Nạp ví qua chuyển khoản ngân hàng',
                        'metadata' => [
                            'deposit_code' => $depositCode,
                            'transaction_id' => $transactionId,
                            'method' => 'bank_transfer',
                        ],
                    ]
                );

                // Cập nhật trạng thái deposit
                DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->update([
                        'status' => 'completed',
                        'gateway_transaction_id' => $transactionId,
                        'gateway_response' => json_encode($payload),
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);

                Log::info('SePay Wallet: Deposit completed', [
                    'user_id' => $deposit->user_id,
                    'deposit_code' => $depositCode,
                    'amount' => $deposit->amount,
                ]);

                return ['status' => 'success', 'message' => 'Wallet deposit processed successfully'];
            });

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('SePay Wallet deposit error: '.$e->getMessage(), [
                'deposit_code' => $depositCode,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Extract payment code từ nội dung chuyển khoản.
     * Hỗ trợ: WDP (wallet deposit), ORD (order), DH (legacy order)
     */
    private function extractPaymentCode(string $content): ?string
    {
        // Wallet deposit: WDP prefix
        if (preg_match('/(WDP[A-Za-z0-9]+)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }
        // Order: ORD prefix
        if (preg_match('/(ORD[A-F0-9]+\d*)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }
        // Legacy: DH + digits
        if (preg_match('/(DH\d+)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }
}
