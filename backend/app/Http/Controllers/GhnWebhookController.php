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
        $allowedIps = config('ghn.webhook_allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            Log::warning('GHN webhook rejected by IP whitelist', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->all();
        $orderCode = $payload['OrderCode'] ?? $payload['order_code'] ?? null;
        $ghnStatus = $payload['Status'] ?? $payload['status'] ?? null;

        if (!$orderCode || !$ghnStatus) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::where('ghn_order_code', $orderCode)->first();
        if (!$order) {
            return response()->json(['message' => 'OK'], 200);
        }

        $result = $this->statusSyncService->syncFromWebhookPayload($order, $payload);
        if (!$result['mapped_status']) {
            Log::info('GHN webhook ignored unknown status', ['order_code' => $orderCode, 'status' => $ghnStatus]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
