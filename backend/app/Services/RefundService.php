<?php

namespace App\Services;

use App\Models\Order;

class RefundService
{
    public function __construct(
        protected ManualRefundService $manualRefundService
    ) {}

    public function processManualRefund(Order $order, array $payload): array
    {
        return $this->manualRefundService->refund($order, $payload);
    }
}
