<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAffiliateController extends Controller
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {}

    /**
     * Danh sách tất cả affiliate users
     * GET /admin/affiliate/users
     */
    public function affiliates(): JsonResponse
    {
        $result = $this->affiliateService->adminGetAffiliates();
        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Danh sách tất cả conversions
     * GET /admin/affiliate/conversions
     */
    public function conversions(): JsonResponse
    {
        $result = $this->affiliateService->adminGetConversions();

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Duyệt hoa hồng
     * PUT /admin/affiliate/conversions/{id}/approve
     */
    public function approveConversion(int $id): JsonResponse
    {
        $result = $this->affiliateService->adminApproveConversion($id);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Hủy hoa hồng
     * PUT /admin/affiliate/conversions/{id}/cancel
     */
    public function cancelConversion(int $id): JsonResponse
    {
        $result = $this->affiliateService->adminCancelConversion($id);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Danh sách yêu cầu rút tiền
     * GET /admin/affiliate/withdrawals
     */
    public function withdrawals(): JsonResponse
    {
        $result = $this->affiliateService->adminGetWithdrawals();

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Duyệt yêu cầu rút tiền
     * PUT /admin/affiliate/withdrawals/{id}/approve
     */
    public function approveWithdrawal(int $id): JsonResponse
    {
        $result = $this->affiliateService->adminApproveWithdrawal($id);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Từ chối yêu cầu rút tiền
     * PUT /admin/affiliate/withdrawals/{id}/reject
     */
    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $result = $this->affiliateService->adminRejectWithdrawal($id, $request->note);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Đánh dấu đã thanh toán
     * PUT /admin/affiliate/withdrawals/{id}/paid
     */
    public function markPaidWithdrawal(int $id): JsonResponse
    {
        $result = $this->affiliateService->adminMarkPaidWithdrawal($id);

        return response()->json($result['body'], $result['status_code']);
    }
}
