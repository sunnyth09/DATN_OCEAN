<?php

namespace App\Http\Controllers;

use App\Services\AdminOrderService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        protected AdminOrderService $adminOrderService
    ) {}

    /**
     * GET — Danh sách đơn hàng
     */
    public function index(Request $request)
    {
        return response()->json(
            $this->adminOrderService->listOrders($request)
        );
    }

    /**
     * GET — Chi tiết đơn hàng
     */
    public function show($id)
    {
        $result = $this->adminOrderService->showOrder($id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * PUT — Cập nhật trạng thái đơn hàng
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'fulfillment_status' => 'nullable|string|in:pending,confirmed,processing,packing,shipping,delivered,completed,cancelled,return_requested,return_approved,return_rejected,returning,warehouse_received,inspection_failed,inspected_ok,returned,refunded',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->adminOrderService->updateStatus($id, $request->only(['fulfillment_status', 'note']));
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * PUT — Cập nhật trạng thái hàng loạt
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer',
            'fulfillment_status' => 'nullable|string|in:pending,confirmed,processing,packing,shipping,delivered,completed,cancelled,return_requested,return_approved,return_rejected,returning,warehouse_received,inspection_failed,inspected_ok,returned,refunded',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->adminOrderService->bulkUpdateStatus(
            $request->only(['order_ids', 'fulfillment_status', 'note'])
        );
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * POST — Đồng bộ đơn hàng lên GHN
     */
    public function syncGHN($id)
    {
        $result = $this->adminOrderService->syncGHN($id);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }
}
