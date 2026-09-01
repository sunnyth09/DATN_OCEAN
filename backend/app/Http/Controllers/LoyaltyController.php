<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\LoyaltyRule;
use App\Models\LuckyWheelPrize;
use App\Models\UserCoupon;
use App\Services\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    protected function getAuthUser(): ?\App\Models\User
    {
        $user = auth('api')->user();
        if ($user instanceof \App\Models\User) {
            return $user;
        }

        $admin = auth('admin')->user();
        if ($admin instanceof \App\Models\Admin) {
            return \App\Models\User::firstOrCreate(
                ['email' => $admin->email],
                [
                    'full_name' => $admin->full_name,
                    'password' => $admin->password,
                    'role' => 'admin',
                    'status' => 'active',
                ]
            );
        }

        $reqUser = request()->user();
        if ($reqUser instanceof \App\Models\User) {
            return $reqUser;
        }

        return null;
    }

    // ─── USER ENDPOINTS ──────────────────────────────────────────────────

    /**
     * GET /api/loyalty/summary
     * Tóm tắt điểm thưởng của user đang đăng nhập.
     */
    public function summary(): JsonResponse
    {
        $user = $this->getAuthUser();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $summary = $this->loyaltyService->getSummary($user->user_id);

        // Add checkin info to summary
        $summary['last_check_in_at'] = $user->last_check_in_at;
        $summary['check_in_streak'] = $user->check_in_streak ?? 0;
        $summary['has_checked_in_today'] = $user->last_check_in_at && Carbon::parse($user->last_check_in_at)->isToday();

        return response()->json(['status' => 'success', 'data' => $summary]);
    }

    /**
     * POST /api/loyalty/check-in
     * Điểm danh hàng ngày nhận xu
     */
    public function checkIn(): JsonResponse
    {
        $user = $this->getAuthUser();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $today = now()->toDateString();
        $lastCheckIn = $user->last_check_in_at ? Carbon::parse($user->last_check_in_at)->toDateString() : null;

        if ($lastCheckIn === $today) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã điểm danh hôm nay rồi.',
                'data' => [
                    'check_in_streak' => $user->check_in_streak,
                    'reward_points' => $user->reward_points,
                ],
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
        $user->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Điểm danh thành công!',
            'data' => [
                'points_earned' => $tx ? $tx->points : 0,
                'check_in_streak' => $user->check_in_streak,
                'reward_points' => $user->reward_points,
            ],
        ]);
    }

    /**
     * GET /api/loyalty/lucky-wheel
     * Lấy danh sách phần thưởng vòng quay
     */
    public function luckyWheelPrizes(): JsonResponse
    {
        $prizes = LuckyWheelPrize::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $prizes,
        ]);
    }

    /**
     * POST /api/loyalty/lucky-wheel/spin
     * Quay vòng quay may mắn
     */
    public function spinLuckyWheel(): JsonResponse
    {
        $user = $this->getAuthUser();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $costPerSpin = 50; // Xu cần cho 1 lần quay

        if ($user->reward_points < $costPerSpin) {
            return response()->json([
                'status' => 'error',
                'message' => "Bạn cần $costPerSpin xu để quay vòng quay.",
            ], 400);
        }

        // Deduct points
        $oldPoints = $user->reward_points;
        $user->reward_points -= $costPerSpin;
        $user->save();

        DB::table('loyalty_transactions')->insert([
            'user_id' => $user->user_id,
            'type' => 'burn',
            'points' => $costPerSpin,
            'balance_before' => $oldPoints,
            'balance_after' => $user->reward_points,
            'description' => 'Chơi vòng quay may mắn',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tính toán phần thưởng dựa trên probability
        $prizes = LuckyWheelPrize::where('is_active', true)->get();
        $totalWeight = $prizes->sum('probability');
        $random = mt_rand(1, (int) ($totalWeight * 100)) / 100;

        $currentWeight = 0;
        $winningPrize = null;
        $winningIndex = 0;

        foreach ($prizes as $index => $prize) {
            $currentWeight += $prize->probability;
            if ($random <= $currentWeight) {
                $winningPrize = $prize;
                $winningIndex = $index;
                break;
            }
        }

        if (! $winningPrize) {
            $winningPrize = $prizes->last();
            $winningIndex = $prizes->count() - 1;
        }

        // Cấp phát phần thưởng
        if ($winningPrize && $winningPrize->type === 'points' && $winningPrize->value > 0) {
            $this->loyaltyService->addPoints(
                $user->user_id,
                $winningPrize->value,
                'earn',
                'Trúng thưởng vòng quay: '.$winningPrize->name
            );
            $user->refresh();
        } elseif ($winningPrize && $winningPrize->type === 'voucher' && $winningPrize->value > 0) {
            $coupon = Coupon::where('type', 'percent')
                ->where('value', $winningPrize->value)
                ->where('is_active', true)
                ->first();
            if ($coupon) {
                UserCoupon::firstOrCreate(
                    ['user_id' => $user->user_id, 'coupon_id' => $coupon->id],
                    ['is_saved' => true, 'used_count' => 0]
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Quay thành công!',
            'data' => [
                'prize' => $winningPrize,
                'prize_index' => $winningIndex,
                'reward_points' => $user->reward_points,
            ],
        ]);
    }

    /**
     * GET /api/loyalty/history?type=earn|burn|expire&per_page=20
     * Lịch sử giao dịch điểm (phân trang).
     */
    public function history(Request $request): JsonResponse
    {
        $user = $this->getAuthUser();
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
        $user = $this->getAuthUser();
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
