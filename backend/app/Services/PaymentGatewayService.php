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

        if ($paymentMethod === 'momo') {
            return $this->handleMoMo($order);
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
            Log::error('VNPay URL generation failed: ' . $e->getMessage());

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

    private function handleMoMo($order): array
    {
        $this->paymentRepository->create([
            'order_id' => $order->order_id,
            'payment_method' => 'momo',
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);

        try {
            $momoUrl = \App\Services\MoMoService::createPaymentUrl($order);

            return [
                'type' => 'redirect',
                'body' => [
                    'status' => 'success',
                    'message' => 'Đơn hàng đã tạo. Đang chuyển đến cổng thanh toán MoMo...',
                    'payment_method' => 'momo',
                    'momo_url' => $momoUrl,
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('MoMo URL generation failed: ' . $e->getMessage());

            return [
                'type' => 'redirect',
                'body' => [
                    'status' => 'warning',
                    'message' => 'Đơn hàng đã tạo nhưng không thể kết nối MoMo. Vui lòng thử thanh toán lại sau.',
                    'data' => [
                        'order_code' => $order->order_code,
                        'grand_total' => $order->grand_total,
                    ],
                ],
            ];
        }
    }
}
