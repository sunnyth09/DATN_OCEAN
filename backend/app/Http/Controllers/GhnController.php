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
        ]);

        $order = Order::where('tracking_number', $data['order_code'])
            ->orWhere('ghn_order_code', $data['order_code'])
            ->first();

        // 1. PhÃ¢n loáº¡i Ocean Express:
        if (($order && $order->carrier === 'ocean_express') || str_starts_with($data['order_code'], 'OE-')) {
            $trackingData = OceanExpressService::getTracking($data['order_code']);
            if ($trackingData) {
                // Tá»± Ä‘á»™ng map tráº¡ng thÃ¡i cá»§a Ocean Express sang format GHN tÆ°Æ¡ng thÃ­ch Ä‘á»ƒ hiá»ƒn thá»‹ UI
                $statusMap = [
                    'pending' => 'ready_to_pick',
                    'ready_to_pick' => 'ready_to_pick',
                    'picking' => 'picking',
                    'stored' => 'storing',
                    'in_transit' => 'transporting',
                    'delivering' => 'delivering',
                    'delivered' => 'delivered',
                    'returning' => 'returning',
                    'returned' => 'returned',
                    'cancelled' => 'cancel',
                    'failed' => 'delivery_fail',
                ];
                $mappedStatus = $statusMap[$trackingData['status']] ?? $trackingData['status'];

                $ghnLogs = [];
                if (!empty($trackingData['logs'])) {
                    foreach ($trackingData['logs'] as $log) {
                        $ghnLogs[] = [
                            'status' => $statusMap[$log['status']] ?? $log['status'],
                            'updated_date' => $log['timestamp'] ?? now()->toIso8601String(),
                            'note' => $log['note'] ?? '',
                        ];
                    }
                }

                // Cáº­p nháº­t tráº¡ng thÃ¡i Ä‘Æ¡n hÃ ng trong DB náº¿u cÃ³ sá»± thay Ä‘á»•i
                if ($order && $mappedStatus) {
                    $orderStatusMap = [
                        'delivered' => 'completed',
                        'cancel' => 'cancelled',
                        'delivery_fail' => 'failed',
                    ];
                    $fulfillmentStatusMap = [
                        'ready_to_pick' => 'ready_to_pick',
                        'picking' => 'picking',
                        'storing' => 'storing',
                        'transporting' => 'in_transit',
                        'delivering' => 'delivering',
                        'delivered' => 'delivered',
                        'returning' => 'returning',
                        'returned' => 'returned',
                        'cancel' => 'cancelled',
                        'delivery_fail' => 'delivery_fail',
                    ];

                    $newFulfillmentStatus = $fulfillmentStatusMap[$mappedStatus] ?? $order->fulfillment_status;
                    $newOrderStatus = $orderStatusMap[$mappedStatus] ?? $order->status;

                    if ($order->fulfillment_status !== $newFulfillmentStatus || $order->status !== $newOrderStatus) {
                        $oldStatus = $order->status;
                        $order->update([
                            'status' => $newOrderStatus,
                            'fulfillment_status' => $newFulfillmentStatus,
                        ]);

                        OrderStatusHistory::create([
                            'order_id' => $order->order_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newOrderStatus,
                            'note' => 'Äá»“ng bá»™ tá»± Ä‘á»™ng tá»« Ocean Express (Tra cá»©u Ä‘Æ¡n)',
                            'source' => 'system',
                            'description' => "Tráº¡ng thÃ¡i váº­n chuyá»ƒn: {$mappedStatus}",
                            'happened_at' => now(),
                        ]);
                    }
                }

                return response()->json([
                    'code' => 200,
                    'message' => 'Success',
                    'data' => [
                        'order_code' => $trackingData['tracking_number'],
                        'status' => $mappedStatus,
                        'status_name' => $trackingData['status_label'] ?? $trackingData['status_name'] ?? $mappedStatus,
                        'log' => $ghnLogs,
                        'to_name' => $trackingData['receiver_name'] ?? '',
                        'to_phone' => $trackingData['receiver_phone'] ?? '',
                        'to_address' => $trackingData['receiver_address'] ?? '',
                        'from_name' => $trackingData['sender_name'] ?? '',
                        'from_phone' => $trackingData['sender_phone'] ?? '',
                        'from_address' => $trackingData['sender_address'] ?? '',
                        'cod_amount' => $trackingData['cod_amount'] ?? 0,
                    ]
                ]);
            }

            return response()->json([
                'code' => 404,
                'message' => 'KhÃ´ng tÃ¬m tháº¥y thÃ´ng tin Ä‘Æ¡n hÃ ng trÃªn há»‡ thá»‘ng Ocean Express',
                'data' => null
            ], 404);
        }

        // 2. Tra cá»©u GHN
        try {
            $detail = GHNService::getOrderDetail($data['order_code']);

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