<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OceanExpressOrderStatusSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OceanExpressWebhookController extends Controller
{
    public function __construct(
        private OceanExpressOrderStatusSyncService $statusSyncService
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->all();
        $trackingNumber = $payload['tracking_number'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $trackingNumber || ! $status) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Tìm đơn hàng theo tracking_number
        $order = Order::where('tracking_number', $trackingNumber)->first();
        if (! $order) {
            // Log warning if needed, but return 200 so webhook stops retrying
            return response()->json(['message' => 'Order not found'], 200);
        }

        $result = $this->statusSyncService->syncFromWebhookPayload($order, $payload);
        
        if (! $result['mapped_status']) {
            Log::info('Ocean Express webhook ignored unknown status', [
                'tracking_number' => $trackingNumber, 
                'status' => $status
            ]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
