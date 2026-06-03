<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SepayController extends Controller
{
    protected PaymentProcessingService $paymentService;

    public function __construct(PaymentProcessingService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handleWebhook(Request $request)
    {
        // 1. Authenticate Request
        // SePay allows sending a custom API Key in the Authorization header: "Apikey <YOUR_SECRET_TOKEN>"
        $authHeader = $request->header('Authorization');
        $apiKey = $authHeader ? str_replace('Apikey ', '', $authHeader) : null;
        
        $expectedKey = env('SEPAY_API_KEY');

        if ($apiKey !== $expectedKey) {
            Log::warning('SePay Webhook: Unauthorized webhook call', [
                'ip' => $request->ip(),
                'received_key' => $apiKey
            ]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // 2. Extract transaction fields from SePay payload
        $transferContent = $payload['code'] ?? $payload['content'] ?? $payload['description'] ?? ''; // Transfer content
        $transferAmount = (float)($payload['transferAmount'] ?? 0);      // Transferred amount
        $transactionId = $payload['id'] ?? '';                           // Transaction code

        Log::info('SePay Webhook: Processing bank transfer', [
            'transaction_id' => $transactionId,
            'content' => $transferContent,
            'amount' => $transferAmount
        ]);

        // 3. Extract order code (e.g., DH171480 or DH12345) from the bank transfer content
        $orderCode = $this->extractOrderCode($transferContent);

        if (!$orderCode) {
            Log::warning('SePay Webhook: Unable to extract order code from transfer content', [
                'content' => $transferContent
            ]);
            return response()->json(['status' => 'error', 'message' => 'Order code not found in content'], 200);
        }

        // 4. Update order and create payment record in database transaction
        try {
            $response = DB::transaction(function () use ($orderCode, $transferAmount, $transactionId, $payload) {
                $order = Order::where('order_code', $orderCode)->lockForUpdate()->first();

                if (!$order) {
                    Log::warning('SePay Webhook: Order not found', ['order_code' => $orderCode]);
                    return ['status' => 'error', 'message' => 'Order not found: ' . $orderCode];
                }

                if ($order->payment_status === 'paid') {
                    return ['status' => 'success', 'message' => 'Order already paid']; // Idempotency
                }

                // Verify amount (allow small rounding difference e.g. < 10 VND)
                if (abs($transferAmount - $order->grand_total) > 10) {
                    Log::error('SePay Webhook: Amount mismatch', [
                        'order' => $order->order_code,
                        'expected' => $order->grand_total,
                        'received' => $transferAmount
                    ]);
                    return ['status' => 'error', 'message' => 'Amount mismatch'];
                }

                // Update order status
                $order->update(['payment_status' => 'paid']);

                // Log the payment
                Payment::create([
                    'order_id' => $order->order_id,
                    'payment_method' => 'bank_transfer',
                    'transaction_code' => $transactionId,
                    'amount' => $transferAmount,
                    'status' => 'success',
                    'paid_at' => now(),
                    'gateway_response' => $payload,
                ]);

                // Clear cart, send email confirmation and send socket updates
                $this->paymentService->dispatchPostPaymentActions($order);

                return ['status' => 'success', 'message' => 'Payment processed successfully'];
            });

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('SePay Webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }

    private function extractOrderCode(string $content): ?string
    {
        // Match ORD followed by uppercase hex characters (the actual format)
        if (preg_match('/(ORD[A-F0-9]+\d*)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }
        // Fallback: legacy DH + digits format
        if (preg_match('/(DH\d+)/i', $content, $matches)) {
            return strtoupper($matches[1]);
        }
        return null;
    }
}
