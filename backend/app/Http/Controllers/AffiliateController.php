<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {}

    /**
     * Đăng ký affiliate
     * POST /profile/affiliate/register
     */
    public function register(Request $request): JsonResponse
    {
        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không thể đăng ký Affiliate của khách hàng.'
            ], 403);
        }

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
        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không có Affiliate profile.'
            ], 403);
        }

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
        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không có thống kê Affiliate.'
            ], 403);
        }

        $userId = auth('api')->id();
        $type = $request->query('type', 'month');
        $result = $this->affiliateService->getStatistics($userId, $type);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Danh sách đơn hàng phát sinh hoa hồng
     * GET /profile/affiliate/conversions
     */
    public function conversions(Request $request): JsonResponse
    {
        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không có đơn hàng Affiliate.'
            ], 403);
        }

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
            'amount' => 'required|numeric|min:1',
            'bank_name' => 'required|string|max:100',
            'bank_account_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:50',
        ]);

        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không thể rút tiền Affiliate.'
            ], 403);
        }

        $userId = auth('api')->id();
        $result = $this->affiliateService->requestWithdrawal($userId, $request->only([
            'amount', 'bank_name', 'bank_account_name', 'bank_account_number'
        ]));

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Lịch sử rút tiền
     * GET /profile/affiliate/withdrawals
     */
    public function withdrawals(Request $request): JsonResponse
    {
        if (auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Tài khoản quản trị không có lịch sử rút tiền Affiliate.'
            ], 403);
        }

        $userId = auth('api')->id();
        $result = $this->affiliateService->getWithdrawals($userId);

        return response()->json($result['body'], $result['status_code']);
    }

    /**
     * Ghi nhận click referral link (Public API)
     * POST /affiliate/track-click
     */
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:20',
            'product_id' => 'nullable|integer',
        ]);

        $userId = auth('api')->id() ?? auth('admin')->id();

        $result = $this->affiliateService->trackClick([
            'referral_code' => $request->referral_code,
            'user_id' => $userId,
            'product_id' => $request->product_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($result['body'], $result['status_code']);
    }
}
