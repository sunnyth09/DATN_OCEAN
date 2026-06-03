<?php

namespace App\Services;

use App\Contracts\PaymentGatewayRefundInterface;
use App\Models\Order;

class ManualRefundService implements PaymentGatewayRefundInterface
{
    public function refund(Order $order, array $payload): array
    {
        return [
            'success' => true,
            'message' => 'Đã ghi nhận hoàn tiền thủ công.',
            'metadata' => [
                'order_code' => $order->order_code,
                'refund_amount' => (float) ($payload['refund_amount'] ?? 0),
                'refund_method' => $payload['refund_method'] ?? null,
            ],
        ];
    }
}
