<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Services\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * =====================================================================
 * RemindAbandonedCart — Artisan Command nhắc nhở giỏ hàng bỏ quên
 * =====================================================================
 *
 * CHẠY: php artisan app:remind-abandoned-cart
 * LỊCH: Mỗi phút (TEST) / Mỗi giờ (Production)
 *
 * LOGIC:
 * 1. Tìm tất cả cart active có ít nhất 1 item
 * 2. Kiểm tra item MỚI NHẤT trong cart → nếu updated_at < threshold → bỏ quên
 * 3. Kiểm tra chống spam (đã gửi trong cooldown chưa)
 * 4. Tặng điểm thưởng + gửi notification
 */
class RemindAbandonedCart extends Command
{
    protected $signature = 'app:remind-abandoned-cart';
    protected $description = 'Nhắc nhở user có giỏ hàng bỏ quên và tặng điểm thưởng';

    /**
     * Số điểm thưởng tặng mỗi lần nhắc nhở
     */
    const REWARD_POINTS = 50;

    /**
     * Số PHÚT giỏ hàng không tương tác để coi là "bỏ quên"
     * TEST: 5 phút | Production: đổi thành 240 (= 4 tiếng)
     */
    const ABANDONED_MINUTES = 1;

    /**
     * Số PHÚT giữa 2 lần gửi thông báo (tránh spam)
     * Đổi thành 1440 (= 24 giờ) để tránh spam ngày nhiều lần
     */
    const COOLDOWN_MINUTES = 1440;

    public function __construct(
        protected LoyaltyService $loyaltyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🛒 [' . now()->format('Y-m-d H:i:s') . '] Kiểm tra giỏ hàng bỏ quên...');

        // ─── Bước 1: Tính mốc thời gian "bỏ quên" ───
        $abandonedThreshold = Carbon::now()->subMinutes(self::COOLDOWN_MINUTES);
        $this->info("  Threshold: items updated trước {$abandonedThreshold->format('H:i:s')} → bỏ quên");

        // ─── Bước 2: Query giỏ hàng bỏ quên ───
        // - Lấy tất cả cart active có items
        // - JOIN subquery: tìm MAX(updated_at) của cart_items cho mỗi cart
        // - Nếu MAX(updated_at) < threshold → toàn bộ giỏ hàng đã lâu không tương tác
        $abandonedCarts = Cart::where('status', 'active')
            ->whereHas('items')  // Phải có ít nhất 1 item
            ->whereDoesntHave('items', function ($query) use ($abandonedThreshold) {
                // Loại bỏ cart NẾU CÒN item nào mới hơn threshold
                // → Cart còn lại = TẤT CẢ items đều cũ hơn threshold = bỏ quên
                $query->where('updated_at', '>=', $abandonedThreshold);
            })
            ->with(['user', 'items'])
            ->get();

        $this->info("  Tìm thấy {$abandonedCarts->count()} giỏ hàng bỏ quên.");

        if ($abandonedCarts->isEmpty()) {
            return 0;
        }

        $successCount = 0;
        $skipCount = 0;

        // ─── Bước 3: Xử lý từng giỏ hàng ───
        foreach ($abandonedCarts as $cart) {
            $user = $cart->user;

            if (!$user || $user->status !== 'active') {
                $this->warn("  ⚠ Cart #{$cart->cart_id}: User không tồn tại hoặc bị banned, bỏ qua.");
                continue;
            }

            try {
                // Lấy thời điểm cập nhật mới nhất của giỏ hàng
                $latestUpdate = $cart->items->max('updated_at');

                // --- Kiểm tra chống spam (FIX LỖI SPAM LIÊN TỤC) ---
                // 1. NGƯỜI DÙNG ĐÃ NHẬN THÔNG BÁO VỀ TRẠNG THÁI GIỎ HÀNG NÀY CHƯA?
                // Nếu có 1 notification lưu sau cả lúc giỏ hàng thay đổi -> đã nhắc nhở xong rồi -> KHÔNG lặp lại
                $alreadyNotifiedForThisCart = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->user_id)
                    ->where('type', AbandonedCartNotification::class)
                    ->where('created_at', '>=', $latestUpdate)
                    ->exists();

                // 2. NGƯỜI DÙNG ĐÃ NHẬN THÔNG BÁO GIỎ HÀNG NÀO KHÁC TRONG 24H QUA CHƯA?
                // (Chống spam nhỡ họ cứ thêm đồ mới xong bỏ quên liên tục)
                $recentNotification = DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->user_id)
                    ->where('type', AbandonedCartNotification::class)
                    ->where('created_at', '>', Carbon::now()->subMinutes(self::ABANDONED_MINUTES))
                    ->exists();

                if ($alreadyNotifiedForThisCart || $recentNotification) {
                    $skipCount++;
                    $this->warn("  ⏭ User #{$user->user_id} ({$user->full_name}): Đã thông báo rồi hoặc nằm trong thời gian cooldown, bỏ qua.");
                    continue;
                }

                // --- Đếm số item ---
                $itemCount = $cart->items->count();

                // --- Đánh dấu giỏ hàng đã được nhắc nhở để cộng điểm khi checkout ---
                $cart->update(['is_abandoned_reminded' => true]);

                // --- Gửi notification (mail + database) ---
                $user->notify(new AbandonedCartNotification($itemCount, 30));

                $this->info("  ✅ Đã nhắc nhở: {$user->full_name} ({$user->email}) - {$itemCount} SP");
                $successCount++;

                // Log để debug
                Log::info("AbandonedCart: Sent notification to user #{$user->user_id} ({$user->email}), {$itemCount} items, +{self::REWARD_POINTS} points");

            } catch (\Exception $e) {
                $this->error("  ❌ Lỗi user #{$user->user_id}: {$e->getMessage()}");
                Log::error("AbandonedCart Error: user #{$user->user_id}: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("🎁 Kết quả: {$successCount} gửi OK, {$skipCount} bỏ qua (cooldown).");
        return 0;
    }
}
