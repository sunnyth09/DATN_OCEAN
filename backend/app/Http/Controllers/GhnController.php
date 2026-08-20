<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\GhnOrderStatusSyncService;
use App\Services\GHNService;
use App\Services\OceanExpressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GhnController extends Controller
{
    public function __construct(
        private GhnOrderStatusSyncService $statusSyncService
    ) {}

    public function calculateFee(Request $request)
    {
        $data = $request->validate([
            'to_district_id' => 'required|integer',
            'to_ward_code' => 'required|string',
            'weight' => 'required|integer|min:1',
            'insurance_value' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string',
        ]);

        try {
            $feeData = GHNService::calculateFee(
                toDistrictId: (int) $data['to_district_id'],
                toWardCode: $data['to_ward_code'],
                weight: (int) $data['weight'],
                insuranceValue: (int) ($data['insurance_value'] ?? 0),
                couponCode: $data['coupon_code'] ?? null
            );

            return response()->json($feeData);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }

    public function getLeadtime(Request $request)
    {
        $data = $request->validate([
            'to_district_id' => 'required|integer',
            'to_ward_code' => 'required|string',
        ]);

        try {
            $leadtime = GHNService::getLeadtime(
                toDistrictId: (int) $data['to_district_id'],
                toWardCode: $data['to_ward_code']
            );

            return response()->json($leadtime);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }

    public function orderDetail(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string',
            'sync' => 'nullable|boolean',
        ]);

        $orderCode = trim($data['order_code']);

        $order = Order::where('tracking_number', $orderCode)
            ->orWhere('ghn_order_code', $orderCode)
            ->first();

        // 1. PhÃ¢n loáº¡i Ocean Express:
        if (($order && $order->carrier === 'ocean_express') || str_starts_with($orderCode, 'OE-')) {
            $trackingData = OceanExpressService::getTracking($orderCode);
            if (empty($trackingData)) {
                return response()->json([
                    'code' => 404,
                    'status' => 'error',
                    'message' => 'KhÃ´ng tÃ¬m tháº¥y thÃ´ng tin Ä‘Æ¡n hÃ ng trÃªn há»‡ thá»‘ng Ocean Express',
                    'data' => null,
                ], 404);
            }

            $rawLogs = $trackingData['tracking_logs'] ?? $trackingData['logs'] ?? [];
            $oeStatus = $trackingData['status'] ?? null;
            $syncService = app(\App\Services\OceanExpressOrderStatusSyncService::class);
            $mappedStatus = $syncService->mapStatus($oeStatus ?? '');

            $latestLog = collect($rawLogs)->sortByDesc('created_at')->first() ?? collect($rawLogs)->sortByDesc('timestamp')->first();
            $logTime = ! empty($latestLog['created_at'])
                ? $latestLog['created_at']
                : (! empty($latestLog['timestamp']) ? $latestLog['timestamp'] : ($trackingData['created_at'] ?? now()->toIso8601String()));

            $syncResult = [
                'changed' => false,
                'history_created' => false,
            ];

            if ($order && $oeStatus && ! empty($data['sync'])) {
                $payload = [
                    'status' => $oeStatus,
                    'timestamp' => $logTime,
                    'note' => $latestLog['note'] ?? $oeStatus,
                    'latitude' => $trackingData['receiver_latitude'] ?? null,
                    'longitude' => $trackingData['receiver_longitude'] ?? null,
                ];

                $syncResult = $syncService->syncFromWebhookPayload($order, $payload);
                $order->refresh();
            }

            $statusName = $trackingData['status_name'] ?? $trackingData['status_label'] ?? $oeStatus;
            $statusDesc = $trackingData['status_description'] ?? ($latestLog['note'] ?? 'ÄÆ¡n hÃ ng Ocean Express');

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => $syncResult['changed'] ? 'ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i Ä‘Æ¡n hÃ ng tá»« Ocean Express' : 'Láº¥y tráº¡ng thÃ¡i váº­n chuyá»ƒn thÃ nh cÃ´ng',
                'data' => [
                    'carrier' => 'Ocean Express',
                    'order_code' => $trackingData['tracking_number'] ?? $orderCode,
                    'ghn_status' => $oeStatus,
                    'status_name' => $statusName,
                    'status_label' => $trackingData['status_label'] ?? $statusName,
                    'status_description' => $statusDesc,
                    'status_badge' => $trackingData['status_badge'] ?? null,
                    'mapped_status' => $mappedStatus,
                    'local_status' => $order?->fulfillment_status ?? $mappedStatus,
                    'changed' => $syncResult['changed'] ?? false,
                    'history_created' => $syncResult['history_created'] ?? false,
                    'happened_at' => $logTime,
                    'location' => $trackingData['receiver_address_detail'] ?? $trackingData['receiver_address'] ?? null,
                    'description' => $statusDesc,
                    'logs' => $rawLogs,
                    'tracking_url' => 'https://oceanexpress.bcbdev.id.vn/tracking?code=' . ($trackingData['tracking_number'] ?? $orderCode),
                    'print_url' => 'https://api.oceanexpress.bcbdev.id.vn/api/v1/public/orders/' . ($trackingData['tracking_number'] ?? $orderCode) . '/label',
                    'raw' => $trackingData,
                ],
            ]);
        }

        // 2. Tra cá»©u GHN
        try {
            $detail = GHNService::getOrderDetail($orderCode);

            // Äá»“ng bá»™ tráº¡ng thÃ¡i Ä‘Æ¡n hÃ ng khi tra cá»©u
            if ($order && isset($detail['data']['status'])) {
                $ghnStatus = $detail['data']['status'];
                $this->statusSyncService->syncFromGhnStatus($order, $ghnStatus);
            }

            return response()->json($detail);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }

    public function cancelOrder(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $orderCode = trim($data['order_code']);
        $reason = $data['reason'] ?? '';

        // 1. ÄÆ¡n Ocean Express (mÃ£ báº¯t Ä‘áº§u OE-)
        if (str_starts_with($orderCode, 'OE-')) {
            $result = OceanExpressService::cancelOrder($orderCode, $reason);

            try {
                $order = Order::where('tracking_number', $orderCode)->first();
                if ($order) {
                    $oldStatus = $order->status;
                    $order->update([
                        'status' => 'cancelled',
                        'fulfillment_status' => 'cancelled',
                    ]);

                    OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'old_status' => $oldStatus,
                        'new_status' => 'cancelled',
                        'note' => $reason,
                        'source' => 'manual',
                        'description' => $reason,
                        'happened_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('GhnController cancelOrder DB update: ' . $e->getMessage());
            }

            return response()->json($result);
        }

        // 2. ÄÆ¡n GHN
        try {
            $result = GHNService::cancelOrder($orderCode);

            $order = Order::where('ghn_order_code', $orderCode)->first();
            if ($order) {
                $oldStatus = $order->status;
                $order->update([
                    'status' => 'cancelled',
                    'fulfillment_status' => 'cancelled',
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'note' => $reason,
                    'source' => 'manual',
                    'description' => $reason,
                    'happened_at' => now(),
                ]);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }

    public function printLabel(Request $request)
    {
        $request->validate([
            'order_code' => 'required|string',
        ]);

        $orderCode = trim($request->order_code);

        // 1. Náº¿u mÃ£ báº¯t Ä‘áº§u báº±ng OE- -> ÄÆ¡n Ocean Express
        if (str_starts_with($orderCode, 'OE-')) {
            $result = OceanExpressService::printLabel($orderCode);
            return response()->json($result);
        }

        // 2. Kiá»ƒm tra náº¿u trong DB lÃ  Ä‘Æ¡n carrier Ocean Express
        try {
            $order = Order::where('tracking_number', $orderCode)
                ->orWhere('ghn_order_code', $orderCode)
                ->first();

            if ($order && ($order->carrier === 'ocean_express' || ($order->tracking_number && str_starts_with($order->tracking_number, 'OE-')))) {
                $result = OceanExpressService::printLabel($order->tracking_number ?? $orderCode);
                return response()->json($result);
            }
        } catch (\Throwable $e) {
            Log::warning('GhnController printLabel Order lookup: ' . $e->getMessage());
        }

        // 3. ÄÆ¡n GHN
        try {
            return response()->json(GHNService::printLabel($orderCode));
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }
}