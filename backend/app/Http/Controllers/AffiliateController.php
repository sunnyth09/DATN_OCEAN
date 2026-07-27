<?php

namespace App\Http\Controllers;

use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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
    ) {}

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
        $request->merge([
            'referral_code' => strtoupper(trim((string) $request->input('referral_code', ''))),
        ]);

        $request->validate([
            'referral_code' => 'required|string|max:20',
            'product_id' => 'nullable|integer',
        ]);

        if ($rateLimited = $this->checkTrackClickRateLimits($request)) {
            return $rateLimited;
        }

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

    private function checkTrackClickRateLimits(Request $request): ?JsonResponse
    {
        $ip = $request->ip() ?: 'unknown';
        $codeHash = md5($request->input('referral_code'));
        $limits = config('affiliate.spam_protection.track_click', []);

        $checks = [
            [
                'name' => 'ip_per_minute',
                'key' => 'affiliate:track-click:ip:minute:'.$ip,
                'max' => (int) ($limits['ip_per_minute'] ?? 30),
                'decay' => 60,
            ],
            [
                'name' => 'ip_per_hour',
                'key' => 'affiliate:track-click:ip:hour:'.$ip,
                'max' => (int) ($limits['ip_per_hour'] ?? 300),
                'decay' => 3600,
            ],
            [
                'name' => 'code_per_minute',
                'key' => 'affiliate:track-click:code:minute:'.$codeHash,
                'max' => (int) ($limits['code_per_minute'] ?? 120),
                'decay' => 60,
            ],
            [
                'name' => 'code_per_hour',
                'key' => 'affiliate:track-click:code:hour:'.$codeHash,
                'max' => (int) ($limits['code_per_hour'] ?? 1000),
                'decay' => 3600,
            ],
        ];

        foreach ($checks as $check) {
            if ($check['max'] <= 0) {
                continue;
            }

            if (RateLimiter::tooManyAttempts($check['key'], $check['max'])) {
                $seconds = RateLimiter::availableIn($check['key']);

                Log::warning('affiliate.track_click.rate_limited', [
                    'limiter' => $check['name'],
                    'ip_hash' => hash('sha256', $ip),
                    'referral_code_hash' => $codeHash,
                    'retry_after' => $seconds,
                ]);

                return response()->json([
                    'status' => false,
                    'message' => "Quá nhiều yêu cầu ghi nhận affiliate. Vui lòng thử lại sau {$seconds} giây.",
                ], 429)->header('Retry-After', $seconds);
            }
        }

        foreach ($checks as $check) {
            if ($check['max'] > 0) {
                RateLimiter::hit($check['key'], $check['decay']);
            }
        }

        return null;
    }
}
