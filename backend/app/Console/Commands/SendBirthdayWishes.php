<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\User;
use App\Models\UserCoupon;
use App\Notifications\BirthdayNotification;
use App\Services\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * =====================================================================
 * SendBirthdayWishes — Artisan Command chúc mừng sinh nhật user
 * =====================================================================
 *
 * CHẠY: php artisan app:send-birthday-wishes
 * LỊCH: Mỗi ngày lúc 00:00 (đăng ký trong routes/console.php)
 *
 * LOGIC HOẠT ĐỘNG:
 * 1. Lấy ngày hiện tại (tháng + ngày)
 * 2. Query tất cả users có date_of_birth trùng tháng + ngày hôm nay
 * 3. Với mỗi user sinh nhật:
 *    a. Tạo mã giảm giá tự động trong bảng coupons
 *    b. Gắn coupon cho user trong bảng user_coupons
 *    c. Gửi notification (email + inbox) qua BirthdayNotification
 * 4. Log kết quả ra console
 */
class SendBirthdayWishes extends Command
{
    /**
     * signature — Tên command khi chạy trên terminal
     *
     * Cú pháp: 'tên-command' — Laravel tự đăng ký command này
     * Chạy bằng: php artisan app:send-birthday-wishes
     */
    protected $signature = 'app:send-birthday-wishes';

    /**
     * description — Mô tả command (hiện khi chạy php artisan list)
     */
    protected $description = 'Gửi lời chúc sinh nhật + mã giảm giá cho user có sinh nhật hôm nay';

    /**
     * handle() — Logic chính của command
     *
     * Được Laravel gọi khi command được thực thi.
     * Return 0 = thành công, 1 = thất bại
     */
    public function handle(LoyaltyService $loyaltyService): int
    {
        // ─── Bước 1: Lấy ngày hiện tại ───
        $today = Carbon::today();
        $this->info("🎂 Kiểm tra sinh nhật ngày: {$today->format('d/m/Y')}");

        // ─── Bước 2: Query users có sinh nhật hôm nay ───
        // whereMonth() → lọc theo tháng sinh
        // whereDay()   → lọc theo ngày sinh
        // whereNotNull() → loại bỏ user chưa cập nhật ngày sinh
        // where('status', 'active') → chỉ lấy user đang hoạt động
        $birthdayUsers = User::whereNotNull('date_of_birth')
            ->where('status', 'active')
            ->whereMonth('date_of_birth', $today->month)
            ->whereDay('date_of_birth', $today->day)
            ->get();

        // Nếu không có ai sinh nhật hôm nay → dừng
        if ($birthdayUsers->isEmpty()) {
            $this->info('  Không có user nào sinh nhật hôm nay.');

            return 0;
        }

        $this->info("  Tìm thấy {$birthdayUsers->count()} user sinh nhật hôm nay!");

        $successCount = 0;

        // ─── Bước 3: Xử lý từng user ───
        foreach ($birthdayUsers as $user) {
            try {
                // --- 3a. Tạo mã giảm giá sinh nhật ---
                // Mã code: BIRTHDAY-{USER_ID}-{MMDD} → đảm bảo unique mỗi năm
                $couponCode = 'BIRTHDAY-'.$user->user_id.'-'.$today->format('md');

                // Kiểm tra xem mã đã tồn tại chưa (tránh tạo trùng nếu command chạy lại)
                $existingCoupon = Coupon::where('code', $couponCode)->first();

                if ($existingCoupon) {
                    $this->warn("  ⚠ User #{$user->user_id} ({$user->full_name}) đã có mã sinh nhật hôm nay, bỏ qua.");

                    continue;
                }

                // Tạo coupon mới trong bảng coupons
                // firstOrCreate() → tạo mới nếu chưa tồn tại, trả về nếu đã có
                $coupon = Coupon::create([
                    'code' => $couponCode,
                    'type' => 'percentage',           // Giảm theo phần trăm
                    'value' => 10,                     // Giảm 10%
                    'max_discount_value' => 50000,                  // Tối đa 50.000đ
                    'min_order_value' => 0,                      // Không yêu cầu đơn tối thiểu
                    'usage_limit' => 1,                      // Chỉ dùng 1 lần
                    'used_count' => 0,
                    'user_usage_limit' => 1,
                    'is_public' => false,                  // Mã riêng, không công khai
                    'is_first_order' => false,
                    'start_date' => $today->toDateString(),
                    'end_date' => $today->copy()->addDays(7)->toDateString(), // Hạn 7 ngày
                    'is_active' => true,
                ]);

                // --- 3b. Gắn coupon cho user trong bảng user_coupons ---
                UserCoupon::create([
                    'user_id' => $user->user_id,
                    'coupon_id' => $coupon->id,
                    'used_count' => 0,
                    'is_saved' => true,    // Tự động lưu vào "mã giảm giá của tôi"
                ]);

                // --- 3c. Gửi notification (mail + database inbox) ---
                // notify() → method từ trait Notifiable trong User model
                // Laravel tự động gọi toMail() và toArray() trong BirthdayNotification
                $user->notify(new BirthdayNotification($couponCode, '10% (tối đa 50.000đ)'));

                // --- 3d. Tích điểm Loyalty mừng sinh nhật ---
                $loyaltyService->earnBirthday($user);

                $this->info("  ✅ Đã gửi chúc mừng sinh nhật cho: {$user->full_name} ({$user->email})");
                $successCount++;

            } catch (\Exception $e) {
                // Bắt lỗi cụ thể từng user → không ảnh hưởng các user khác
                $this->error("  ❌ Lỗi với user #{$user->user_id}: {$e->getMessage()}");
            }
        }

        $this->info("🎉 Hoàn thành! Đã gửi {$successCount}/{$birthdayUsers->count()} lời chúc sinh nhật.");

        return 0; // Return 0 = command thành công
    }
}
