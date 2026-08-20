<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\GhnOrderStatusSyncService;
use App\Services\GHNService;
use App\Services\OceanExpressService;
use Illuminate\Http\Request;

class GhnController extends Controller
{
    public function __construct(
        private GhnOrderStatusSyncService $statusSyncService
    ) {}

    public function calculateFee(Request $request)
    {
        $data = $request->validate([
            // Legacy GHN fields (kept for backward compat — ignored for Ocean Express)
            'district_id' => 'nullable',
            'to_district_id' => 'nullable',
            'service_type_id' => 'nullable|integer',
            // Ocean Express: ward_code is a string location ID like 'VN-01-00004'
            'ward_code' => 'required_without:to_ward_code|string',
            'to_ward_code' => 'required_without:ward_code|string',
            // Also accept receiver_location_id directly (preferred)
            'receiver_location_id' => 'nullable|string',
            'weight' => 'nullable|integer|min:10',
        ]);

        // Priority: receiver_location_id > to_ward_code > ward_code
        $receiverLocationId = $data['receiver_location_id']
            ?? $data['to_ward_code']
            ?? $data['ward_code'];

        $weight = (int) ($data['weight'] ?? 500);

        $fee = OceanExpressService::calculateRate($receiverLocationId, $weight);

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => [
                'total' => $fee,
            ],
        ]);
    }

    public function getLeadtime(Request $request)
    {
        $data = $request->validate([
            'district_id' => 'required_without:to_district_id|integer',
            'to_district_id' => 'required_without:district_id|integer',
            'ward_code' => 'required_without:to_ward_code|string',
            'to_ward_code' => 'required_without:ward_code|string',
            'service_id' => 'nullable|integer',
        ]);

        return response()->json(GHNService::calculateLeadtime($data));
    }

    public function orderDetail(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string',
            'sync' => 'nullable|boolean',
        ]);

        $order = Order::where('tracking_number', $data['order_code'])
            ->orWhere('ghn_order_code', $data['order_code'])
            ->first();

        // 1. Nếu là Ocean Express
        if ($order && $order->tracking_number === $data['order_code']) {
            $detail = \App\Services\OceanExpressService::getTracking($data['order_code']);

            if (empty($detail)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy vận đơn Ocean Express hoặc chưa có dữ liệu hành trình.',
                ], 404);
            }

            $oeStatus = $detail['status'] ?? null;
            $mappedStatus = match ($oeStatus) {
                'ready_to_pick' => 'awaiting_pickup',
                'picking', 'in_hub', 'delivering', 'shipping' => 'shipping',
                'delivered', 'completed' => 'delivered',
                'returned' => 'returned',
                'cancelled' => 'cancelled',
                default => null,
            };

            // Tìm log mới nhất (hỗ trợ cả tracking_logs và logs, created_at và timestamp)
            $rawLogs = $detail['tracking_logs'] ?? $detail['logs'] ?? [];
            $logs = collect($rawLogs)->map(function ($log) {
                if (! is_array($log)) {
                    return null;
                }

                return [
                    'status' => $log['status'] ?? null,
                    'note' => $log['note'] ?? null,
                    'timestamp' => $log['created_at'] ?? $log['timestamp'] ?? null,
                ];
            })->filter()->sortByDesc('timestamp');

            $latestLog = $logs->first();
            $logTime = ! empty($latestLog['timestamp'])
                ? \Carbon\Carbon::parse($latestLog['timestamp'])
                : (! empty($detail['created_at']) ? \Carbon\Carbon::parse($detail['created_at']) : now());
            $logDesc = $latestLog['note'] ?? ($oeStatus ? "Trạng thái: {$oeStatus}" : 'Đơn hàng Ocean Express');
            $location = $detail['receiver_address_detail'] ?? $detail['receiver_address'] ?? null;

            $syncResult = [
                'changed' => false,
                'history_created' => false,
            ];

            // Nếu cờ sync = true, cập nhật trạng thái vào cơ sở dữ liệu (chỉ cập nhật nếu tiến tới, không downgrade lùi)
            if (! empty($data['sync']) && $mappedStatus && $mappedStatus !== $order->fulfillment_status) {
                $statusWeights = [
                    'pending' => 10,
                    'confirmed' => 20,
                    'processing' => 30,
                    'packing' => 40,
                    'awaiting_pickup' => 45,
                    'shipping' => 50,
                    'delivered' => 60,
                    'completed' => 70,
                ];

                $currentWeight = $statusWeights[$order->fulfillment_status] ?? 0;
                $targetWeight = $statusWeights[$mappedStatus] ?? 0;

                // Chỉ cập nhật nếu không bị lùi trạng thái và không phải terminal status
                if ($targetWeight >= $currentWeight && ! in_array($order->fulfillment_status, ['completed', 'cancelled', 'returned'])) {
                    $oldStatus = $order->fulfillment_status;
                    $order->update(['fulfillment_status' => $mappedStatus]);

                    \App\Models\OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'old_status' => $oldStatus,
                        'new_status' => $mappedStatus,
                        'note' => 'Đồng bộ từ Ocean Express',
                        'source' => 'carrier_api',
                        'description' => $logDesc,
                        'happened_at' => $logTime,
                        'location' => $location,
                        'ghn_status' => $oeStatus,
                    ]);

                    $syncResult['changed'] = true;
                    $syncResult['history_created'] = true;
                    $order->refresh();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => $syncResult['changed'] ? 'Đã cập nhật trạng thái đơn hàng từ Ocean Express' : 'Lấy trạng thái vận chuyển thành công',
                'data' => [
                    'order_code' => $data['order_code'],
                    'ghn_status' => $oeStatus, // Giữ tên key ghn_status cho FE tương thích
                    'mapped_status' => $mappedStatus,
                    'local_status' => $order->fulfillment_status,
                    'changed' => $syncResult['changed'],
                    'history_created' => $syncResult['history_created'],
                    'happened_at' => $logTime->toIso8601String(),
                    'location' => $location,
                    'description' => $logDesc,
                    'raw' => $detail,
                ],
            ]);
        }

        // 2. Fallback cho đơn GHN cũ (nếu có)
        try {
            $detail = GHNService::getOrderDetail($data['order_code']);
            if (empty($detail)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy vận đơn GHN hoặc GHN chưa trả dữ liệu.',
                ], 404);
            }

            $syncResult = null;
            if ($order && ! empty($data['sync'])) {
                $syncResult = $this->statusSyncService->syncFromDetail($order, $detail);
                $order->refresh();
            }

            $ghnStatus = $detail['status'] ?? $detail['Status'] ?? null;
            $mappedStatus = $ghnStatus ? $this->statusSyncService->mapGhnStatus($ghnStatus) : null;

            return response()->json([
                'status' => 'success',
                'message' => $syncResult
                    ? ($syncResult['message'] ?? 'Đã đồng bộ trạng thái GHN')
                    : 'Lấy trạng thái GHN thành công',
                'data' => [
                    'order_code' => $data['order_code'],
                    'ghn_status' => $ghnStatus,
                    'mapped_status' => $mappedStatus,
                    'local_status' => $order?->fulfillment_status,
                    'changed' => $syncResult['changed'] ?? false,
                    'history_created' => $syncResult['history_created'] ?? false,
                    'happened_at' => $syncResult['happened_at'] ?? ($detail['UpdatedDate'] ?? $detail['updated_date'] ?? null),
                    'location' => $syncResult['location'] ?? ($detail['CurrentWarehouseName'] ?? $detail['current_warehouse_name'] ?? null),
                    'description' => $syncResult['description'] ?? ($detail['status_name'] ?? $detail['StatusName'] ?? $ghnStatus),
                    'raw' => $detail,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancelOrder(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string',
            'reason' => 'required|string|max:255',
        ]);

        $order = Order::where('tracking_number', $data['order_code'])
            ->orWhere('ghn_order_code', $data['order_code'])
            ->first();
            
        if ($order && $order->tracking_number === $data['order_code']) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Ocean Express hiện không hỗ trợ hủy đơn qua API. Vui lòng liên hệ tổng đài.'
            ], 400);
        }

        try {
            $result = GHNService::cancelOrder($data['order_code']);

            if ($order && (($result['code'] ?? 200) === 200)) {
                $oldStatus = $order->fulfillment_status;
                $order->update([
                    'fulfillment_status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => $data['reason'],
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'note' => $data['reason'],
                    'source' => 'manual',
                    'description' => $data['reason'],
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
        $request->validate(['order_code' => 'required|string']);

        $order = Order::where('tracking_number', $request->order_code)
            ->orWhere('ghn_order_code', $request->order_code)
            ->first();

        if ($order && $order->tracking_number === $request->order_code) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Ocean Express hiện không hỗ trợ in vận đơn qua API.'
            ], 400);
        }

        try {
            return response()->json(GHNService::printLabel($request->order_code));
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage()], 400);
        }
    }
}
