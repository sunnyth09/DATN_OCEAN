<?php

namespace App\Services;

use App\Contracts\PaymentGatewayRefundInterface;
use App\Models\Order;
use App\Models\RefundTransaction;

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
                    'reference_type' => RefundTransaction::class,
                    'reference_id' => $payload['refund_transaction_id'] ?? null,
                    'metadata' => [
                        'order_code' => $order->order_code,
                        'return_request_id' => $payload['return_request_id'] ?? null,
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
