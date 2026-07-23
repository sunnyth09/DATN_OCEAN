<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MoMoController extends Controller
{
    private $partnerCode;
    private $accessKey;
    private $secretKey;
    private $endpoint;
    private $redirectUrl;
    private $ipnUrl;

    public function __construct()
    {
        $this->partnerCode = config('momo.partner_code');
        $this->accessKey   = config('momo.access_key');
        $this->secretKey   = config('momo.secret_key');
        $this->endpoint    = config('momo.endpoint');
        $this->redirectUrl = config('momo.redirect_url');
        $this->ipnUrl      = config('momo.ipn_url');
    }

    /**
     * Xử lý URL Return khi người dùng thanh toán xong và được redirect về Website
     */
    public function momoReturn(Request $request)
    {
        // Kiểm tra mã đơn hàng
        if (!$request->has('orderId')) {
            return response()->json([
                "status" => "error",
                "message" => "Thiếu mã đơn hàng (orderId)"
            ], 400);
        }

        // Xác thực chữ ký
        if (!$this->verifySignature($request->all())) {
            return response()->json([
                "status" => "error",
                "message" => "Chữ ký trả về không hợp lệ"
            ], 400);
        }

        $resultCode = $request->input('resultCode');
        $orderCode = $request->input('orderId');

        // CHỈ TRẢ VỀ TRẠNG THÁI CHO CLIENT/UI, KHÔNG UPDATE DATABASE Ở ĐÂY.
        // IPN (Webhook) SẼ LÀ NƠI DUY NHẤT CẬP NHẬT TRẠNG THÁI THANH TOÁN (Tránh user fake Return URL).

        if ($resultCode == "0") {
            return response()->json([
                "status" => "success",
                "payment_status" => "paid",
                "message" => "Thanh toán thành công (Đang chờ xác nhận từ IPN)",
                "data" => [
                    "order_code" => $orderCode,
                    "grand_total" => $request->input('amount'),
                    "transaction_no" => $request->input('transId'),
                    "pay_date" => $request->input('responseTime'),
                ],
                "method" => "MOMO",
            ]);
        }
        
        return response()->json([
            "status" => "error",
            "payment_status" => "failed",
            "message" => "Thanh toán thất bại hoặc người dùng đã hủy giao dịch",
            "data" => [
                "order_code" => $orderCode,
                "grand_total" => $request->input('amount')
            ],
            "method" => "MOMO",
        ]);
    }



    /**
     * Tích hợp URL IPN (Webhook) để tự động cập nhật trạng thái đơn hàng ngầm phía Server
     */
    public function momoIpn(Request $request)
    {
        $data = $request->all();

        if (empty($data)) {
            return response()->json(["message" => "No data received"], 400);
        }

        // Xác thực chữ ký của MoMo gọi qua Server
        if (!$this->verifySignature($data)) {
            Log::error('MoMo IPN - Invalid Signature', $data);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature'
            ], 400);
        }

        $resultCode = $request->input('resultCode');
        $orderCode  = $request->input('orderId');
        $amount     = (float) ($request->input('amount') ?? 0);
        $transId    = (string) ($request->input('transId') ?? '');

        // ── Nạp ví qua MoMo (mã có tiền tố WDP) ──
        // Trước đây nhánh này bị thiếu → tiền nạp ví MoMo bị tra như đơn hàng, 404,
        // và không bao giờ được credit vào ví. Xử lý idempotent + khóa dòng ở đây.
        if (is_string($orderCode) && str_starts_with($orderCode, 'WDP')) {
            return $this->handleMomoWalletDeposit($orderCode, $amount, $transId, $data, (int) $resultCode);
        }

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($orderCode, $resultCode, $amount, $transId) {
                // Khóa dòng đơn để chống race giữa nhiều callback (MoMo IPN + Return) trên cùng đơn.
                $order = \App\Models\Order::where('order_code', $orderCode)->lockForUpdate()->first();

                if (!$order) {
                    Log::warning("MoMo IPN: Không tìm thấy hóa đơn $orderCode");
                    return response()->json(['message' => 'Order not found'], 404);
                }

                if ((int) $resultCode === 0) {
                    // Defense-in-depth: đối chiếu số tiền MoMo báo với tổng đơn (chữ ký đã phủ
                    // amount, nhưng vẫn kiểm tra để chống sai lệch cấu hình/manh mối gian lận).
                    if (abs($amount - (float) $order->grand_total) > 10) {
                        Log::error('MoMo IPN: Amount mismatch', [
                            'order_code' => $orderCode,
                            'expected'   => $order->grand_total,
                            'received'   => $amount,
                        ]);
                        return response()->json(['message' => 'Amount mismatch'], 400);
                    }

                    if ($order->payment_status !== 'paid') {
                        $order->update(['payment_status' => 'paid']);
                        \App\Models\Payment::where('order_id', $order->order_id)
                                            ->where('payment_method', 'momo')
                                            ->update(['status' => 'completed']);

                        \App\Models\OrderStatusHistory::create([
                            'order_id'   => $order->order_id,
                            'new_status' => $order->fulfillment_status,
                            'note'       => 'Thanh toán MoMo thành công',
                        ]);

                        Log::info("Thanh toán MoMo thành công đơn hàng số: $orderCode");
                    }
                } else {
                    Log::warning("Thanh toán MoMo thất bại đơn hàng số: $orderCode");
                    \App\Models\Payment::where('order_id', $order->order_id)
                                        ->where('payment_method', 'momo')
                                        ->where('status', 'pending')
                                        ->update(['status' => 'failed']);
                }

                // MoMo yêu cầu status code 204 No Content khi xử lý đúng đắn IPN
                return response()->noContent();
            });
        } catch (\Exception $e) {
            Log::error('MoMo IPN Handling Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Xử lý nạp ví qua MoMo (mã WDP) — idempotent, khóa dòng, đối chiếu số tiền.
     */
    private function handleMomoWalletDeposit(string $depositCode, float $amount, string $transId, array $payload, int $resultCode)
    {
        if ($resultCode !== 0) {
            \Illuminate\Support\Facades\DB::table('wallet_deposits')
                ->where('deposit_code', $depositCode)
                ->where('status', 'pending')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            return response()->noContent();
        }

        try {
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($depositCode, $amount, $transId, $payload) {
                $deposit = \Illuminate\Support\Facades\DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->lockForUpdate()
                    ->first();

                if (!$deposit) {
                    Log::warning('MoMo Wallet: Deposit code not found', ['code' => $depositCode]);
                    return ['status' => 'error', 'message' => 'Deposit code not found'];
                }

                // Chặn cả trạng thái terminal 'completed' VÀ 'failed'. deposit_code là
                // duy nhất mỗi lần init (WDP + random, không tái dùng khi retry), nên một
                // deposit đã 'failed' không bao giờ được nạp lại hợp lệ → chặn để tránh
                // double-credit khi IPN failed đến trước rồi success đến sau cùng code.
                if (in_array($deposit->status, ['completed', 'failed'], true)) {
                    return ['status' => 'success', 'message' => 'Deposit already in terminal state']; // Idempotency
                }

                if (abs($amount - (float) $deposit->amount) > 10) {
                    Log::error('MoMo Wallet: Amount mismatch', [
                        'deposit_code' => $depositCode,
                        'expected'     => $deposit->amount,
                        'received'     => $amount,
                    ]);
                    return ['status' => 'error', 'message' => 'Amount mismatch'];
                }

                app(\App\Services\WalletService::class)->credit(
                    userId: $deposit->user_id,
                    amount: (float) $deposit->amount,
                    type: 'deposit',
                    opts: [
                        'description' => 'Nạp ví qua MoMo',
                        'metadata'    => [
                            'deposit_code'   => $depositCode,
                            'transaction_id' => $transId,
                            'method'         => 'momo',
                        ],
                    ]
                );

                \Illuminate\Support\Facades\DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->update([
                        'status'                 => 'completed',
                        'gateway_transaction_id' => $transId,
                        'gateway_response'       => json_encode($payload),
                        'completed_at'           => now(),
                        'updated_at'             => now(),
                    ]);

                Log::info('MoMo Wallet: Deposit completed', [
                    'user_id'      => $deposit->user_id,
                    'deposit_code' => $depositCode,
                    'amount'       => $deposit->amount,
                ]);

                return ['status' => 'success', 'message' => 'Wallet deposit processed'];
            });

            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('MoMo Wallet deposit error: ' . $e->getMessage(), ['deposit_code' => $depositCode]);
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Hàm dùng chung để kiểm tra Signature hợp lệ (dùng chuẩn MoMo API v2)
     */
    private function verifySignature(array $data)
    {
        $momoSignature = $data['signature'] ?? '';

        // Dựa vào tài liệu MoMo v2, thứ tự tạo chữ ký so sánh từ Dữ liệu MoMo gửi
        $rawHash = "accessKey=" . $this->accessKey .
            "&amount=" . ($data['amount'] ?? '') .
            "&extraData=" . ($data['extraData'] ?? '') .
            "&message=" . ($data['message'] ?? '') .
            "&orderId=" . ($data['orderId'] ?? '') .
            "&orderInfo=" . ($data['orderInfo'] ?? '') .
            "&orderType=" . ($data['orderType'] ?? '') .
            "&partnerCode=" . ($data['partnerCode'] ?? '') .
            "&payType=" . ($data['payType'] ?? '') .
            "&requestId=" . ($data['requestId'] ?? '') .
            "&responseTime=" . ($data['responseTime'] ?? '') .
            "&resultCode=" . ($data['resultCode'] ?? '') .
            "&transId=" . ($data['transId'] ?? '');

        // Tính toán chữ ký HMAC SHA256 để so sánh
        $expectedSignature = hash_hmac('sha256', $rawHash, $this->secretKey);

        return hash_equals($expectedSignature, $momoSignature);
    }
}
