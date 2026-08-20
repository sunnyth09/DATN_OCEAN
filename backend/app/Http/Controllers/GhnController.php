<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\GhnOrderStatusSyncService;
use App\Services\GHNService;
use App\Services\OceanExpressOrderStatusSyncService;
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

        try {
            $order = Order::where('tracking_number', $orderCode)
                ->orWhere('ghn_order_code', $orderCode)
                ->orWhere('order_code', $orderCode)
                ->first();

            // 1. Phân loại Ocean Express:
            $isOceanExpress = ($order && $order->carrier === 'ocean_express')
                || str_starts_with($orderCode, 'OE-')
                || ($order && str_starts_with((string) $order->tracking_number, 'OE-'));

            if ($isOceanExpress) {
                $trackingNumber = ($order && $order->tracking_number) ? $order->tracking_number : $orderCode;
                $trackingData = OceanExpressService::getTracking($trackingNumber);

                if (empty($trackingData)) {
                    return response()->json([
                        'code' => 404,
                        'status' => 'error',
                        'message' => 'Không tìm thấy thông tin đơn hàng trên hệ thống Ocean Express',
                        'data' => null,
                    ], 404);
                }

                $rawLogs = $trackingData['tracking_logs'] ?? $trackingData['logs'] ?? [];
                $oeStatus = $trackingData['status'] ?? null;
                $syncService = app(OceanExpressOrderStatusSyncService::class);
                $mappedStatus = $syncService->mapStatus($oeStatus ?? '');

                $latestLog = collect($rawLogs)->sortByDesc('created_at')->first() ?? collect($rawLogs)->sortByDesc('timestamp')->first();
                $rawLogTime = ! empty($latestLog['created_at'])
                    ? $latestLog['created_at']
                    : (! empty($latestLog['timestamp']) ? $latestLog['timestamp'] : ($trackingData['created_at'] ?? now()->toIso8601String()));

                $logTimeCarbon = $syncService->parseHappenedAt(['timestamp' => $rawLogTime]);
                $logTime = $logTimeCarbon->toIso8601String();

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
                $statusDesc = $trackingData['status_description'] ?? ($latestLog['note'] ?? 'Đơn hàng Ocean Express');

                $finalTrackingNumber = $trackingData['tracking_number'] ?? $trackingNumber;
                $trackingBaseUrl = rtrim((string) config('ocean_express.tracking_url', 'https://oceanexpress.bcbdev.id.vn/tracking'), '/');
                $apiBaseUrl = rtrim((string) config('ocean_express.api_url', 'https://api.oceanexpress.bcbdev.id.vn/api/v1'), '/');

                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'message' => $syncResult['changed'] ? 'Đã cập nhật trạng thái đơn hàng từ Ocean Express' : 'Lấy trạng thái vận chuyển thành công',
                    'data' => [
                        'carrier' => 'Ocean Express',
                        'order_code' => $finalTrackingNumber,
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
                        'tracking_url' => $trackingBaseUrl.'/'.urlencode($finalTrackingNumber),
                        'print_url' => $apiBaseUrl.'/public/tracking/'.urlencode($finalTrackingNumber).'/label',
                        'raw' => $trackingData,
                    ],
                ]);
            }

            // 2. Tra cứu GHN
            $ghnCode = ($order && $order->ghn_order_code) ? $order->ghn_order_code : (($order && $order->tracking_number) ? $order->tracking_number : $orderCode);
            $detail = GHNService::getOrderDetail($ghnCode);

            if (empty($detail)) {
                return response()->json([
                    'code' => 404,
                    'status' => 'error',
                    'message' => 'Không tìm thấy thông tin đơn hàng trên hệ thống GHN',
                    'data' => null,
                ], 404);
            }

            // Đồng bộ trạng thái đơn hàng khi tra cứu
            $ghnStatus = $detail['status'] ?? $detail['Status'] ?? null;
            if ($order && $ghnStatus) {
                $this->statusSyncService->syncFromGhnStatus($order, $ghnStatus);
            }

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'Lấy thông tin vận chuyển GHN thành công',
                'data' => [
                    'carrier' => 'Giao Hàng Nhanh',
                    'order_code' => $detail['order_code'] ?? $ghnCode,
                    'ghn_status' => $ghnStatus,
                    'status_name' => $detail['status_name'] ?? $ghnStatus,
                    'status_label' => $detail['status_name'] ?? $ghnStatus,
                    'status_description' => $detail['note'] ?? $ghnStatus,
                    'status_badge' => null,
                    'mapped_status' => $ghnStatus ? $this->statusSyncService->mapGhnStatus($ghnStatus) : $order?->fulfillment_status,
                    'local_status' => $order?->fulfillment_status,
                    'changed' => false,
                    'history_created' => false,
                    'happened_at' => $detail['updated_date'] ?? now()->toIso8601String(),
                    'location' => $detail['warehouse_name'] ?? null,
                    'description' => $detail['note'] ?? $ghnStatus,
                    'logs' => $detail['log'] ?? $detail['logs'] ?? [],
                    'tracking_url' => 'https://donhang.ghn.vn/?order_code='.urlencode($ghnCode),
                    'print_url' => null,
                    'raw' => $detail,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('GhnController orderDetail error: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Lỗi tra cứu đơn hàng: '.$e->getMessage(),
            ], 400);
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

        try {
            $order = Order::where('tracking_number', $orderCode)
                ->orWhere('ghn_order_code', $orderCode)
                ->orWhere('order_code', $orderCode)
                ->first();

            // 1. Đơn Ocean Express (mã bắt đầu OE- hoặc carrier ocean_express)
            $isOceanExpress = ($order && $order->carrier === 'ocean_express')
                || str_starts_with($orderCode, 'OE-')
                || ($order && str_starts_with((string) $order->tracking_number, 'OE-'));

            if ($isOceanExpress) {
                $trackingNumber = ($order && $order->tracking_number) ? $order->tracking_number : $orderCode;
                $result = OceanExpressService::cancelOrder($trackingNumber, $reason);

                if ($order && ($result['code'] ?? 0) === 200) {
                    $oldStatus = $order->fulfillment_status;
                    $order->update([
                        'fulfillment_status' => 'cancelled',
                    ]);

                    OrderStatusHistory::create([
                        'order_id' => $order->order_id,
                        'old_status' => $oldStatus,
                        'new_status' => 'cancelled',
                        'note' => $reason,
                        'source' => 'manual',
                        'description' => 'Hủy vận đơn Ocean Express: '.$reason,
                        'happened_at' => now(),
                    ]);
                }

                return response()->json($result);
            }

            // 2. Đơn GHN
            $ghnCode = ($order && $order->ghn_order_code) ? $order->ghn_order_code : $orderCode;
            $result = GHNService::cancelOrder($ghnCode);

            if ($order && ($result['code'] ?? 0) === 200) {
                $oldStatus = $order->fulfillment_status;
                $order->update([
                    'fulfillment_status' => 'cancelled',
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'note' => $reason,
                    'source' => 'manual',
                    'description' => 'Hủy vận đơn GHN: '.$reason,
                    'happened_at' => now(),
                ]);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('GhnController cancelOrder error: '.$e->getMessage());

            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Lỗi hủy vận đơn: '.$e->getMessage(),
            ], 400);
        }
    }

    public function printLabel(Request $request)
    {
        $request->validate([
            'order_code' => 'required|string',
        ]);

        $orderCode = trim($request->order_code);

        try {
            $order = Order::where('tracking_number', $orderCode)
                ->orWhere('ghn_order_code', $orderCode)
                ->orWhere('order_code', $orderCode)
                ->first();

            // 1. Đơn Ocean Express
            $isOceanExpress = ($order && $order->carrier === 'ocean_express')
                || str_starts_with($orderCode, 'OE-')
                || ($order && str_starts_with((string) $order->tracking_number, 'OE-'));

            if ($isOceanExpress) {
                $trackingNumber = ($order && $order->tracking_number) ? $order->tracking_number : $orderCode;
                $result = OceanExpressService::printLabel($trackingNumber);

                return response()->json($result);
            }

            // 2. Đơn GHN
            $ghnCode = ($order && $order->ghn_order_code) ? $order->ghn_order_code : $orderCode;

            return response()->json(GHNService::printLabel($ghnCode));
        } catch (\Throwable $e) {
            Log::error('GhnController printLabel error: '.$e->getMessage());

            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Lỗi in vận đơn: '.$e->getMessage(),
            ], 400);
        }
    }
}
