<?php

namespace App\Services;

use App\Repositories\PaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    public function __construct(
        protected PaymentRepository $paymentRepository
    ) {}

    public function handlePayment($order, string $paymentMethod, Request $request): array
    {
        if ($paymentMethod === 'vnpay') {
            return $this->handleVNPay($order, $request);
        }

        if ($paymentMethod === 'bank_transfer') {
            return $this->handleBanking($order);
        }

        return [
            'type' => 'normal',
            'body' => null,
        ];
    }

    private function handleVNPay($order, Request $request): array
    {
        $this->paymentRepository->create([
            'order_id' => $order->order_id,
            'payment_method' => 'vnpay',
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);

        try {
            $vnpayUrl = VNPayService::createPaymentUrl($order, $request->ip());

            return [
                'type' => 'redirect',
                'body' => [
                    'status' => 'success',
                    'message' => 'Đơn hàng đã tạo. Đang chuyển đến cổng thanh toán VNPay...',
                    'payment_method' => 'vnpay',
                    'vnpay_url' => $vnpayUrl,
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('VNPay URL generation failed: '.$e->getMessage());

            return [
                'type' => 'redirect',
                'body' => [
                    'status' => 'warning',
                    'message' => 'Đơn hàng đã tạo nhưng không thể kết nối VNPay. Vui lòng vào "Đơn hàng của tôi" để thử thanh toán lại.',
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                    ],
                ],
            ];
        }
    }

    private function handleBanking($order): array
    {
        // Lấy thông tin tài khoản ngân hàng từ config (hoạt động đúng khi config:cache)
        $bankBin = config('services.bank.bin');
        $bankAccount = config('services.bank.account_number');
        $accountName = config('services.bank.account_name');
        $amount = (int) $order->grand_total;
        $orderCode = $order->order_code;

        // Sepay QR API
        $qrUrl = "https://qr.sepay.vn/img?acc={$bankAccount}&bank={$bankBin}&amount={$amount}&des=".urlencode($orderCode);

        $this->paymentRepository->create([
            'order_id' => $order->order_id,
            'payment_method' => 'bank_transfer',
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);

        return [
            'type' => 'redirect',
            'body' => [
                'status' => 'success',
                'message' => 'Đơn hàng đã tạo. Vui lòng chuyển khoản để hoàn tất.',
                'payment_method' => 'bank_transfer',
                'banking_info' => [
                    'bank_bin' => $bankBin,
                    'account_number' => $bankAccount,
                    'account_name' => $accountName,
                    'amount' => $amount,
                    'order_code' => $orderCode,
                    'qr_url' => $qrUrl,
                ],
                'data' => [
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total,
                ],
            ],
        ];
    }
}
