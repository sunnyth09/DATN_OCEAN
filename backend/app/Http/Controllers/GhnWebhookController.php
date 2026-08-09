<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\GhnOrderStatusSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GhnWebhookController extends Controller
{
    public function __construct(
        private GhnOrderStatusSyncService $statusSyncService
    ) {}

    public function handle(Request $request)
    {
        // Xác thực token/HMAC + IP whitelist đã được middleware 'carrier.webhook:ghn'
        // xử lý trước khi tới đây (xem App\Http\Middleware\VerifyCarrierWebhook).
        $payload = $request->all();
        $orderCode = $payload['OrderCode'] ?? $payload['order_code'] ?? null;
        $ghnStatus = $payload['Status'] ?? $payload['status'] ?? null;

        if (! $orderCode || ! $ghnStatus) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::where('ghn_order_code', $orderCode)->first();
        if (! $order) {
            return response()->json(['message' => 'OK'], 200);
        }

        $result = $this->statusSyncService->syncFromWebhookPayload($order, $payload);
        if (! $result['mapped_status']) {
            Log::info('GHN webhook ignored unknown status', ['order_code' => $orderCode, 'status' => $ghnStatus]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
