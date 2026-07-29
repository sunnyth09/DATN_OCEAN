<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\GhnOrderStatusSyncService;
use App\Services\GHNService;
use Illuminate\Http\Request;

class GhnController extends Controller
{
    public function __construct(
        private GhnOrderStatusSyncService $statusSyncService
    ) {}

    public function calculateFee(Request $request)
    {
        $data = $request->validate([
            'district_id' => 'required_without:to_district_id|integer',
            'to_district_id' => 'required_without:district_id|integer',
            'ward_code' => 'required_without:to_ward_code|string',
            'to_ward_code' => 'required_without:ward_code|string',
            'weight' => 'nullable|integer|min:10',
            'service_type_id' => 'nullable|integer',
        ]);

        return response()->json(GHNService::calculateFee($data));
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

        $detail = GHNService::getOrderDetail($data['order_code']);
        if (empty($detail)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy vận đơn GHN hoặc GHN chưa trả dữ liệu.',
            ], 404);
        }

        $order = Order::where('ghn_order_code', $data['order_code'])->first();
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
    }

    public function cancelOrder(Request $request)
    {
        $data = $request->validate([
            'order_code' => 'required|string',
            'reason' => 'required|string|max:255',
        ]);

        $result = GHNService::cancelOrder($data['order_code']);

        $order = Order::where('ghn_order_code', $data['order_code'])->first();
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
    }

    public function printLabel(Request $request)
    {
        $request->validate(['order_code' => 'required|string']);

        return response()->json(GHNService::printLabel($request->order_code));
    }
}
