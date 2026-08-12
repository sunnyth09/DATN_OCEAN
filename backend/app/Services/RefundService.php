<?php

namespace App\Services;

use App\Models\Order;

/**
 * RefundService — Xử lý hoàn tiền cho đơn hàng bị huỷ hoặc trả hàng.
 *
 * Delegate logic hoàn tiền thủ công cho ManualRefundService.
 * Hỗ trợ hoàn vào ví hoặc chuyển khoản ngân hàng.
 */
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
