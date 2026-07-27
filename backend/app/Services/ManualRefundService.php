<?php

namespace App\Services;

use App\Contracts\PaymentGatewayRefundInterface;
use App\Models\Order;

class ManualRefundService implements PaymentGatewayRefundInterface
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function refund(Order $order, array $payload): array
    {
        $refundAmount = (float) ($payload['refund_amount'] ?? 0);
        $refundMethod = $payload['refund_method'] ?? null;

        if ($refundMethod === 'wallet') {
            $this->walletService->credit(
                $order->user_id,
                $refundAmount,
                'refund',
                [
                    'description' => "Hoàn tiền trả hàng cho đơn {$order->order_code}",
                    'reference_type' => Order::class,
                    'reference_id' => $order->order_id,
                    'metadata' => [
                        'order_code' => $order->order_code,
                    ],
                ]
            );

            return [
                'success' => true,
                'message' => 'Đã hoàn tiền tự động vào ví khách hàng.',
                'metadata' => [
                    'order_code' => $order->order_code,
                    'refund_amount' => $refundAmount,
                    'refund_method' => 'wallet',
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'Đã ghi nhận hoàn tiền thủ công.',
            'metadata' => [
                'order_code' => $order->order_code,
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
            ],
        ];
    }
}
