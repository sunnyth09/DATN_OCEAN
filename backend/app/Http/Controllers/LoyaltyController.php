<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyRule;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LoyaltyController
 *
 * User routes:
 *   GET  /api/loyalty/summary         → Điểm hiện tại + thống kê
 *   GET  /api/loyalty/history         → Lịch sử giao dịch (paginated)
 *   GET  /api/loyalty/rules           → Xem cấu hình earn/burn
 *   POST /api/loyalty/preview-burn    → Preview discount trước checkout
 *
 * Admin routes:
 *   GET  /admin/loyalty/rules                 → Danh sách rules
 *   PUT  /admin/loyalty/rules/{key}           → Cập nhật rule
 *   POST /admin/loyalty/users/{userId}/adjust → Điều chỉnh điểm thủ công
 */
class LoyaltyController extends Controller
{
    public function __construct(
        protected LoyaltyService $loyaltyService,
    ) {}

    // ─── USER ENDPOINTS ──────────────────────────────────────────────────

    /**
     * GET /api/loyalty/summary
     * Tóm tắt điểm thưởng của user đang đăng nhập.
     */
    public function summary(): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $summary = $this->loyaltyService->getSummary($user->user_id);
        
        // Add checkin info to summary
        $summary['last_check_in_at'] = $user->last_check_in_at;
        $summary['check_in_streak'] = $user->check_in_streak ?? 0;
        $summary['has_checked_in_today'] = $user->last_check_in_at && \Carbon\Carbon::parse($user->last_check_in_at)->isToday();

        return response()->json(['status' => 'success', 'data' => $summary]);
    }

    /**
     * POST /api/loyalty/check-in
     * Điểm danh hàng ngày nhận xu
     */
    public function checkIn(): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $today = now()->toDateString();
        $lastCheckIn = $user->last_check_in_at ? \Carbon\Carbon::parse($user->last_check_in_at)->toDateString() : null;

        if ($lastCheckIn === $today) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã điểm danh hôm nay rồi.',
                'data' => [
                    'check_in_streak' => $user->check_in_streak,
                    'reward_points' => $user->reward_points,
                ]
            ], 400);
        }

        $yesterday = now()->subDay()->toDateString();
        
        if ($lastCheckIn === $yesterday) {
            $user->check_in_streak += 1;
        } else {
            $user->check_in_streak = 1;
        }
        
        $user->last_check_in_at = now();
        $user->save();

        $tx = $this->loyaltyService->earnDailyCheckIn($user, $user->check_in_streak);

        return response()->json([
            'status' => 'success',
            'message' => 'Điểm danh thành công!',
            'data' => [
                'points_earned' => $tx ? $tx->points : 0,
                'check_in_streak' => $user->check_in_streak,
                'reward_points' => $user->reward_points,
            ]
        ]);
    }



    /**
     * GET /api/loyalty/history?type=earn|burn|expire&per_page=20
     * Lịch sử giao dịch điểm (phân trang).
     */
    public function history(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $type = $request->type;

        $history = $this->loyaltyService->getHistory($user->user_id, $perPage, $type);

        // Transform để thêm label
        $history->getCollection()->transform(fn ($tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'type_label' => $tx->typeLabel(),
            'points' => $tx->points,
            'sign' => in_array($tx->type, ['earn', 'refund', 'adjust']) ? '+' : '-',
            'balance_before' => $tx->balance_before,
            'balance_after' => $tx->balance_after,
            'description' => $tx->description,
            'expires_at' => $tx->expires_at?->toISOString(),
            'is_expired' => $tx->isExpired(),
            'created_at' => $tx->created_at?->toISOString(),
        ]);

        return response()->json(['status' => 'success', 'data' => $history]);
    }

    /**
     * GET /api/loyalty/rules
     * Danh sách quy tắc earn/burn (chỉ active, dùng cho user xem).
     */
    public function rules(): JsonResponse
    {
        $rules = LoyaltyRule::active()
            ->select(['key', 'type', 'name', 'description', 'points_per_unit', 'vnd_per_point',
                'min_points', 'max_points_per_order', 'max_burn_percent', 'earn_expiry_days'])
            ->get()
            ->groupBy('type');

        return response()->json(['status' => 'success', 'data' => $rules]);
    }

    /**
     * POST /api/loyalty/preview-burn
     * Preview số tiền giảm nếu dùng X điểm khi checkout.
     *
     * Body: { points_to_use: int, order_subtotal: float }
     */
    public function previewBurn(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'points_to_use' => 'required|integer|min:0',
            'order_subtotal' => 'required|numeric|min:0',
        ]);

        $result = $this->loyaltyService->previewBurn(
            $user->user_id,
            (int) $request->points_to_use,
            (float) $request->order_subtotal,
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    // ─── ADMIN ENDPOINTS ─────────────────────────────────────────────────

    /**
     * GET /admin/loyalty/rules
     * Danh sách tất cả rules (kể cả inactive) cho admin.
     */
    public function adminListRules(): JsonResponse
    {
        $rules = LoyaltyRule::orderBy('type')->orderBy('key')->get();

        return response()->json(['status' => 'success', 'data' => $rules]);
    }

    /**
     * PUT /admin/loyalty/rules/{key}
     * Cập nhật một rule theo key.
     */
    public function adminUpdateRule(Request $request, string $key): JsonResponse
    {
        $rule = LoyaltyRule::where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:300',
            'points_per_unit' => 'sometimes|numeric|min:0',
            'vnd_per_point' => 'sometimes|numeric|min:0',
            'min_points' => 'sometimes|integer|min:0',
            'max_points_per_order' => 'nullable|integer|min:1',
            'max_burn_percent' => 'nullable|numeric|min:0|max:100',
            'earn_expiry_days' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Đã cập nhật rule '{$key}'",
            'data' => $rule->fresh(),
        ]);
    }

    /**
     * POST /admin/loyalty/users/{userId}/adjust
     * Admin điều chỉnh điểm thủ công cho user.
     */
    public function adminAdjust(Request $request, int $userId): JsonResponse
    {
        $admin = auth('admin')->user();

        $request->validate([
            'delta' => 'required|integer|not_in:0',
            'description' => 'required|string|max:300',
        ]);

        try {
            $tx = $this->loyaltyService->adjustPoints(
                userId: $userId,
                delta: (int) $request->delta,
                description: $request->description,
                adminId: $admin->admin_id,
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Đã điều chỉnh điểm',
                'data' => $tx,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /admin/loyalty/users/{userId}/history
     * Lịch sử giao dịch của một user cụ thể (admin xem).
     */
    public function adminUserHistory(Request $request, int $userId): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 100);
        $type = $request->type;

        $balance = $this->loyaltyService->getBalance($userId);
        $history = $this->loyaltyService->getHistory($userId, $perPage, $type);

        return response()->json([
            'status' => 'success',
            'data' => [
                'current_balance' => $balance,
                'transactions' => $history,
            ],
        ]);
    }
}
