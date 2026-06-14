<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AffiliateController — Quản lý Affiliate của khách hàng.
 *
 * Authorization: Toàn bộ routes qua middleware 'customer.only'
 * (đặt ở routes/api.php hoặc constructor).
 * EnsureCustomerOnly tự động chặn admin/staff và trả 403.
 *
 * Trước đây mỗi method phải lặp lại:
 *   if (auth('admin')->check()) return response()->json([...], 403);
 * Đã được loại bỏ hoàn toàn — middleware xử lý.
 */
class AffiliateController extends Controller
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {
        // Middleware 'customer.only' được áp dụng ở route level (routes/api.php)
        // Laravel 12 không hỗ trợ $this->middleware() trong controller constructor.
    }

    /**
     * Đăng ký affiliate
     * POST /profile/affiliate/register
     */
    public function register(Request $request): JsonResponse
    {
        $userId = auth('api')->id();
        $result = $this->affiliateService->register($userId);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Lấy thông tin affiliate profile
     * GET /profile/affiliate/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $userId = auth('api')->id();
        $result = $this->affiliateService->getProfile($userId);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Thống kê hoa hồng
     * GET /profile/affiliate/statistics?type=day|month|year
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = auth('api')->id();
        $type   = $request->query('type', 'month');
        $result = $this->affiliateService->getStatistics($userId, $type);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Danh sách đơn hàng phát sinh hoa hồng
     * GET /profile/affiliate/conversions
     */
    public function conversions(Request $request): JsonResponse
    {
        $userId = auth('api')->id();
        $result = $this->affiliateService->getConversions($userId);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Gửi yêu cầu rút tiền
     * POST /profile/affiliate/withdrawals
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount'              => 'required|numeric|min:1',
            'bank_name'           => 'required|string|max:100',
            'bank_account_name'   => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:50',
        ]);

        $userId = auth('api')->id();
        $result = $this->affiliateService->requestWithdrawal($userId, $request->only([
            'amount', 'bank_name', 'bank_account_name', 'bank_account_number',
        ]));

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Lịch sử rút tiền
     * GET /profile/affiliate/withdrawals
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $userId = auth('api')->id();
        $result = $this->affiliateService->getWithdrawals($userId);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Ghi nhận click referral link (Public API — không qua customer.only)
     * POST /affiliate/track-click
     */
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:20',
            'product_id'    => 'nullable|integer',
        ]);

        $userId = auth('api')->id() ?? auth('admin')->id();

        $result = $this->affiliateService->trackClick([
            'referral_code' => $request->referral_code,
            'user_id'       => $userId,
            'product_id'    => $request->product_id,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return response()->json($result['body'], $result['status_code']);
    }
}
