<?php

namespace App\Services;

use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * LoyaltyService — Trung tâm xử lý điểm thưởng
 *
 * Earn entry points:
 *   - earnFromOrder()       → gọi từ OrderService khi đơn COMPLETED
 *   - earnFirstOrder()      → gọi từ OrderService khi là đơn đầu tiên
 *   - earnFromReferral()    → gọi từ AffiliateService
 *   - earnBirthday()        → gọi từ Artisan command (scheduler)
 *   - earnFromReview()      → gọi từ ProductCommentController
 *   - earnAbandonedCart()   → thay RemindAbandonedCart hardcode
 *
 * Burn entry points:
 *   - previewBurn()         → preview discount trước khi checkout
 *   - burnPoints()          → gọi từ OrderService khi checkout dùng điểm
 *   - refundPoints()        → gọi từ ReturnRequestService khi hoàn đơn
 *
 * Admin:
 *   - adjustPoints()        → admin cộng/trừ điểm thủ công
 *
 * Job:
 *   - expirePoints()        → Artisan command hàng ngày expire điểm cũ
 */
class LoyaltyService
{
    // ─── EARN METHODS ───────────────────────────────────────────────────

    /**
     * Earn điểm khi đơn hàng COMPLETED.
     * Points = grand_total / 10000 * points_per_unit (làm tròn xuống)
     */
    public function earnFromOrder(User $user, Order $order): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('ORDER_COMPLETE');
        if (!$rule) return null;

        // 1 point / 10.000đ
        $points = (int) floor(($order->grand_total / 10000) * $rule->points_per_unit);

        if ($points <= 0) return null;

        return $this->recordEarn(
            user: $user,
            points: $points,
            rule: $rule,
            referenceType: Order::class,
            referenceId: $order->order_id,
            description: "Tích điểm đơn hàng #{$order->order_code} ({$this->formatMoney($order->grand_total)}đ)",
        );
    }

    /**
     * Earn bonus khi đặt đơn hàng đầu tiên.
     */
    public function earnFirstOrder(User $user, Order $order): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('FIRST_ORDER');
        if (!$rule) return null;

        $points = (int) $rule->points_per_unit;
        if ($points <= 0) return null;

        return $this->recordEarn(
            user: $user,
            points: $points,
            rule: $rule,
            referenceType: Order::class,
            referenceId: $order->order_id,
            description: "Bonus đơn hàng đầu tiên #{$order->order_code}",
        );
    }

    /**
     * Earn điểm khi bạn bè mua hàng thành công (referral).
     *
     * @param User  $referrer  Người giới thiệu (nhận điểm)
     * @param Order $order     Đơn hàng của người được giới thiệu
     */
    public function earnFromReferral(User $referrer, Order $order): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('REFERRAL');
        if (!$rule) return null;

        $points = (int) $rule->points_per_unit;
        if ($points <= 0) return null;

        return $this->recordEarn(
            user: $referrer,
            points: $points,
            rule: $rule,
            referenceType: Order::class,
            referenceId: $order->order_id,
            description: "Điểm giới thiệu: bạn bè đặt đơn #{$order->order_code}",
        );
    }

    /**
     * Earn điểm sinh nhật cho user.
     * Nên gọi từ Artisan command hàng ngày.
     */
    public function earnBirthday(User $user): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('BIRTHDAY');
        if (!$rule) return null;

        // Kiểm tra đã nhận điểm sinh nhật năm nay chưa
        $alreadyEarned = LoyaltyTransaction::forUser($user->user_id)
            ->where('type', 'earn')
            ->where('reference_type', 'birthday')
            ->whereYear('created_at', now()->year)
            ->exists();

        if ($alreadyEarned) return null;

        $points = (int) $rule->points_per_unit;

        return $this->recordEarn(
            user: $user,
            points: $points,
            rule: $rule,
            referenceType: 'birthday',
            referenceId: null,
            description: 'Quà sinh nhật tháng ' . now()->month,
        );
    }

    /**
     * Earn điểm khi viết review sản phẩm.
     *
     * @param User $user
     * @param int  $commentId  ID của ProductComment
     */
    public function earnFromReview(User $user, int $commentId): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('REVIEW');
        if (!$rule) return null;

        // Tránh earn nhiều lần cho cùng 1 comment
        $alreadyEarned = LoyaltyTransaction::forUser($user->user_id)
            ->where('reference_type', 'product_comment')
            ->where('reference_id', $commentId)
            ->exists();

        if ($alreadyEarned) return null;

        $points = (int) $rule->points_per_unit;

        return $this->recordEarn(
            user: $user,
            points: $points,
            rule: $rule,
            referenceType: 'product_comment',
            referenceId: $commentId,
            description: 'Tích điểm viết đánh giá sản phẩm',
        );
    }

    /**
     * Earn điểm khi có giỏ hàng bỏ quên (thay hardcode trong RemindAbandonedCart).
     *
     * @param User $user
     * @param int  $cartId  ID của Cart
     */
    public function earnAbandonedCart(User $user, int $cartId): ?LoyaltyTransaction
    {
        $rule = LoyaltyRule::findByKey('ABANDONED_CART');
        if (!$rule) return null;

        $points = (int) $rule->points_per_unit;

        return $this->recordEarn(
            user: $user,
            points: $points,
            rule: $rule,
            referenceType: 'cart',
            referenceId: $cartId,
            description: 'Điểm thưởng nhắc giỏ hàng bỏ quên',
        );
    }

    // ─── BURN METHODS ───────────────────────────────────────────────────

    /**
     * Preview: Tính số tiền giảm nếu dùng X điểm (không ghi transaction).
     *
     * @param  int   $userId
     * @param  int   $pointsToUse   Số điểm muốn dùng (0 = dùng tối đa cho phép)
     * @param  float $orderSubtotal
     * @return array {
     *     eligible: bool,
     *     points_to_use: int,
     *     discount_amount: float,
     *     message: string,
     *     current_balance: int,
     * }
     */
    public function previewBurn(int $userId, int $pointsToUse, float $orderSubtotal): array
    {
        $rule = LoyaltyRule::findByKey('REDEEM_DISCOUNT');

        if (!$rule) {
            return $this->burnError('Chức năng đổi điểm chưa được cấu hình.', $userId);
        }

        $currentBalance = $this->getBalance($userId);

        if ($currentBalance < $rule->min_points) {
            return $this->burnError(
                "Cần ít nhất {$rule->min_points} điểm để đổi (bạn có {$currentBalance} điểm).",
                $userId,
                $currentBalance
            );
        }

        // Tính điểm tối đa được phép dùng
        $maxByBalance = min($currentBalance, $rule->max_points_per_order ?? PHP_INT_MAX);
        $maxByPercent = $rule->max_burn_percent
            ? (int) floor(($orderSubtotal * $rule->max_burn_percent / 100) / $rule->vnd_per_point)
            : PHP_INT_MAX;

        $maxAllowed = min($maxByBalance, $maxByPercent);

        // Nếu user truyền 0 → dùng tối đa
        $actualPoints = $pointsToUse === 0
            ? $maxAllowed
            : min($pointsToUse, $maxAllowed);

        if ($actualPoints < $rule->min_points) {
            return $this->burnError(
                "Không đủ điểm hoặc đơn hàng quá nhỏ để áp dụng.",
                $userId,
                $currentBalance
            );
        }

        $discountAmount = round($actualPoints * $rule->vnd_per_point, 2);
        $discountAmount = min($discountAmount, $orderSubtotal); // không giảm quá đơn hàng

        return [
            'eligible'        => true,
            'points_to_use'   => $actualPoints,
            'discount_amount' => $discountAmount,
            'vnd_per_point'   => $rule->vnd_per_point,
            'current_balance' => $currentBalance,
            'message'         => "Dùng {$actualPoints} điểm = giảm " . $this->formatMoney($discountAmount) . 'đ',
        ];
    }

    /**
     * Burn điểm khi checkout — gọi từ OrderService trong DB::transaction.
     *
     * @param  User  $user
     * @param  int   $pointsToUse
     * @param  Order $order
     * @return LoyaltyTransaction
     * @throws \Exception nếu không đủ điểm
     */
    public function burnPoints(User $user, int $pointsToUse, Order $order): LoyaltyTransaction
    {
        if ($pointsToUse <= 0) {
            throw new \InvalidArgumentException('Số điểm burn phải > 0');
        }

        $currentBalance = $this->getBalance($user->user_id);

        if ($currentBalance < $pointsToUse) {
            throw new \Exception("Không đủ điểm. Hiện có: {$currentBalance}, cần: {$pointsToUse}");
        }

        $newBalance = $currentBalance - $pointsToUse;

        // Cập nhật balance trên user (atomic)
        DB::table('users')
            ->where('user_id', $user->user_id)
            ->decrement('reward_points', $pointsToUse);

        $rule = LoyaltyRule::findByKey('REDEEM_DISCOUNT');
        $discountAmount = round($pointsToUse * ($rule?->vnd_per_point ?? 100), 2);

        return LoyaltyTransaction::create([
            'user_id'        => $user->user_id,
            'type'           => 'burn',
            'points'         => $pointsToUse,
            'balance_before' => $currentBalance,
            'balance_after'  => $newBalance,
            'reference_type' => Order::class,
            'reference_id'   => $order->order_id,
            'description'    => "Đổi {$pointsToUse} điểm = giảm " . $this->formatMoney($discountAmount) . "đ cho đơn #{$order->order_code}",
        ]);
    }

    /**
     * Hoàn điểm khi đơn hàng bị huỷ/trả hàng.
     * Gọi từ ReturnRequestService hoặc OrderController khi admin cancel.
     */
    public function refundPoints(User $user, Order $order): ?LoyaltyTransaction
    {
        // Tìm giao dịch burn gốc của đơn này
        $burnTx = LoyaltyTransaction::forUser($user->user_id)
            ->where('type', 'burn')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->order_id)
            ->first();

        if (!$burnTx) return null; // Không có burn thì không hoàn

        // Kiểm tra đã hoàn rồi chưa
        $alreadyRefunded = LoyaltyTransaction::forUser($user->user_id)
            ->where('type', 'refund')
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->order_id)
            ->exists();

        if ($alreadyRefunded) return null;

        $currentBalance = $this->getBalance($user->user_id);
        $newBalance     = $currentBalance + $burnTx->points;

        DB::table('users')
            ->where('user_id', $user->user_id)
            ->increment('reward_points', $burnTx->points);

        return LoyaltyTransaction::create([
            'user_id'        => $user->user_id,
            'type'           => 'refund',
            'points'         => $burnTx->points,
            'balance_before' => $currentBalance,
            'balance_after'  => $newBalance,
            'reference_type' => Order::class,
            'reference_id'   => $order->order_id,
            'description'    => "Hoàn {$burnTx->points} điểm từ đơn #{$order->order_code}",
        ]);
    }

    // ─── ADMIN ADJUST ────────────────────────────────────────────────────

    /**
     * Admin cộng/trừ điểm thủ công.
     *
     * @param int    $userId
     * @param int    $delta       Dương = cộng, âm = trừ
     * @param string $description
     * @param int    $adminId     ID của admin thực hiện
     */
    public function adjustPoints(int $userId, int $delta, string $description, int $adminId): LoyaltyTransaction
    {
        $user           = User::findOrFail($userId);
        $currentBalance = $this->getBalance($userId);
        $points         = abs($delta);
        $type           = 'adjust';

        if ($delta > 0) {
            DB::table('users')->where('user_id', $userId)->increment('reward_points', $points);
            $newBalance = $currentBalance + $points;
        } else {
            if ($currentBalance < $points) {
                throw new \Exception("Không thể trừ {$points} điểm. Số dư hiện tại: {$currentBalance}");
            }
            DB::table('users')->where('user_id', $userId)->decrement('reward_points', $points);
            $newBalance = $currentBalance - $points;
        }

        return LoyaltyTransaction::create([
            'user_id'        => $userId,
            'type'           => $type,
            'points'         => $points,
            'balance_before' => $currentBalance,
            'balance_after'  => $newBalance,
            'reference_type' => 'admin',
            'reference_id'   => $adminId,
            'description'    => $description . ($delta < 0 ? " (-{$points}đ)" : " (+{$points}đ)"),
        ]);
    }

    // ─── EXPIRY JOB ─────────────────────────────────────────────────────

    /**
     * Expire các điểm earn đã quá hạn.
     * Gọi từ Artisan Command (scheduler hàng ngày).
     *
     * @return int Số lượng user bị expire
     */
    public function expirePoints(): int
    {
        $expiredCount = 0;

        // Lấy tất cả transactions earn đã quá hạn chưa được expire
        $expiredTxs = LoyaltyTransaction::pendingExpiry()
            ->select(['id', 'user_id', 'points', 'expires_at'])
            ->with('user:user_id,reward_points')
            ->get();

        // Group theo user
        $grouped = $expiredTxs->groupBy('user_id');

        foreach ($grouped as $userId => $txs) {
            $totalExpiredPoints = $txs->sum('points');

            DB::transaction(function () use ($userId, $totalExpiredPoints, $txs, &$expiredCount) {
                $currentBalance = $this->getBalance($userId);

                // Không thể expire nhiều hơn balance hiện tại
                $actualExpire = min($totalExpiredPoints, $currentBalance);

                if ($actualExpire <= 0) {
                    // Đánh dấu đã expire nhưng không trừ điểm (balance đã 0)
                    $txs->each(fn ($tx) => $tx->update(['expired_at' => now()]));
                    return;
                }

                DB::table('users')
                    ->where('user_id', $userId)
                    ->decrement('reward_points', $actualExpire);

                $newBalance = $currentBalance - $actualExpire;

                // Ghi một transaction expire tổng hợp
                LoyaltyTransaction::create([
                    'user_id'        => $userId,
                    'type'           => 'expire',
                    'points'         => $actualExpire,
                    'balance_before' => $currentBalance,
                    'balance_after'  => $newBalance,
                    'reference_type' => 'system',
                    'reference_id'   => null,
                    'description'    => "Hết hạn {$actualExpire} điểm tích lũy",
                ]);

                // Đánh dấu các earn tx đã được expire
                $txs->each(fn ($tx) => $tx->update(['expired_at' => now()]));

                $expiredCount++;

                Log::info("LoyaltyExpiry: User #{$userId} expired {$actualExpire} points. New balance: {$newBalance}");
            });
        }

        return $expiredCount;
    }

    // ─── QUERY HELPERS ───────────────────────────────────────────────────

    /**
     * Lấy số dư điểm hiện tại của user (từ DB, không cache để tránh race condition).
     */
    public function getBalance(int $userId): int
    {
        return (int) DB::table('users')
            ->where('user_id', $userId)
            ->value('reward_points') ?? 0;
    }

    /**
     * Lấy lịch sử giao dịch có phân trang.
     */
    public function getHistory(int $userId, int $perPage = 20, ?string $type = null)
    {
        $query = LoyaltyTransaction::forUser($userId)
            ->orderByDesc('created_at');

        if ($type && in_array($type, ['earn', 'burn', 'expire', 'adjust', 'refund'])) {
            $query->where('type', $type);
        }

        return $query->paginate($perPage);
    }

    /**
     * Tóm tắt thống kê loyalty của user.
     */
    public function getSummary(int $userId): array
    {
        $currentBalance = $this->getBalance($userId);

        $stats = LoyaltyTransaction::forUser($userId)
            ->selectRaw("
                SUM(CASE WHEN type IN ('earn','refund','adjust') THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN type = 'burn' THEN points ELSE 0 END) as total_burned,
                SUM(CASE WHEN type = 'expire' THEN points ELSE 0 END) as total_expired
            ")
            ->first();

        // Điểm sắp hết hạn (trong 30 ngày tới)
        $expiringSoon = LoyaltyTransaction::forUser($userId)
            ->activeEarns()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->sum('points');

        return [
            'current_balance'  => $currentBalance,
            'total_earned'     => (int) ($stats->total_earned ?? 0),
            'total_burned'     => (int) ($stats->total_burned ?? 0),
            'total_expired'    => (int) ($stats->total_expired ?? 0),
            'expiring_soon'    => (int) $expiringSoon,
            'expiring_in_days' => 30,
        ];
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────

    /**
     * Ghi transaction earn và cập nhật balance user.
     */
    private function recordEarn(
        User        $user,
        int         $points,
        LoyaltyRule $rule,
        string      $referenceType,
        ?int        $referenceId,
        string      $description,
    ): LoyaltyTransaction {
        $currentBalance = $this->getBalance($user->user_id);
        $newBalance     = $currentBalance + $points;

        DB::table('users')
            ->where('user_id', $user->user_id)
            ->increment('reward_points', $points);

        return LoyaltyTransaction::create([
            'user_id'        => $user->user_id,
            'type'           => 'earn',
            'points'         => $points,
            'balance_before' => $currentBalance,
            'balance_after'  => $newBalance,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'description'    => $description,
            'expires_at'     => $rule->calcExpiryDate(),
        ]);
    }

    private function burnError(string $message, int $userId, ?int $balance = null): array
    {
        return [
            'eligible'        => false,
            'points_to_use'   => 0,
            'discount_amount' => 0,
            'current_balance' => $balance ?? $this->getBalance($userId),
            'message'         => $message,
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
