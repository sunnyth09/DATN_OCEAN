<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGatewayRefundInterface
{
    /**
     * @return array{success: bool, message: string, metadata?: array<string, mixed>}
     */
    public function refund(Order $order, array $payload): array;
}
