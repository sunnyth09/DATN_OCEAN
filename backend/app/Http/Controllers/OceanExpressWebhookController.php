<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\OceanExpressOrderStatusSyncService;
use App\Services\ReturnRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OceanExpressWebhookController extends Controller
{
    public function __construct(
        private OceanExpressOrderStatusSyncService $statusSyncService,
        private ReturnRequestService $returnRequestService
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->all();
        $trackingNumber = $payload['tracking_number'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $trackingNumber || ! $status) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // 1. Tìm đơn hàng theo tracking_number
        $order = Order::where('tracking_number', $trackingNumber)->first();
        if ($order) {
            $result = $this->statusSyncService->syncFromWebhookPayload($order, $payload);

            if (! $result['mapped_status']) {
                Log::info('Ocean Express webhook ignored unknown status', [
                    'tracking_number' => $trackingNumber,
                    'status' => $status,
                ]);
            }

            return response()->json(['message' => 'OK'], 200);
        }

        // 2. Tìm yêu cầu hoàn hàng theo return_tracking_code
        $returnRequest = ReturnRequest::where('return_tracking_code', $trackingNumber)->first();
        if ($returnRequest) {
            $this->returnRequestService->syncFromOceanExpressWebhook($returnRequest, $payload);

            return response()->json(['message' => 'OK'], 200);
        }

        return response()->json(['message' => 'Tracking number not found'], 200);
    }
}
