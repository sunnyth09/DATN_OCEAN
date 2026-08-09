<?php

/**
 * =====================================================================
 * routes/console.php — Đăng ký Scheduled Tasks (Cron Jobs)
 * =====================================================================
 *
 * File này là nơi đăng ký các command chạy tự động theo lịch.
 * Laravel Scheduler sẽ kiểm tra file này MỖI PHÚT (qua crontab)
 * và thực thi các command nào đến giờ chạy.
 *
 * CÚ PHÁP:
 *   Schedule::command('tên-command')->frequency();
 *
 * CÁC FREQUENCY PHỔ BIẾN:
 *   ->everyMinute()      — Mỗi phút
 *   ->hourly()           — Mỗi giờ (phút 0)
 *   ->dailyAt('00:00')   — Mỗi ngày lúc 00:00
 *   ->weekly()           — Mỗi tuần (Chủ nhật 00:00)
 *   ->monthly()          — Mỗi tháng (ngày 1, 00:00)
 *
 * CÁC OPTION BỔ SUNG:
 *   ->withoutOverlapping()  — Không chạy nếu lần trước chưa xong
 *   ->onOneServer()         — Chỉ chạy trên 1 server (khi deploy nhiều server)
 *   ->appendOutputTo(path)  — Ghi output ra file log
 *
 * CÁCH HOẠT ĐỘNG VỚI DOCKER:
 *   Crontab trong container chạy mỗi phút:
 *   * * * * * cd /var/www && php artisan schedule:run >> /var/www/storage/logs/cron.log 2>&1
 *   → Laravel sẽ check xem command nào cần chạy tại thời điểm đó → thực thi
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ── Command mặc định của Laravel (có thể xóa) ──
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =====================================================================
// ██ SCHEDULED TASKS — CÁC TÁC VỤ TỰ ĐỘNG
// =====================================================================

/**
 * ── 2. Nhắc nhở giỏ hàng bỏ quên ──
 *
 * Chạy mỗi giờ, quét giỏ hàng bỏ quên (xem ABANDONED_MINUTES trong command).
 *
 * ->hourly()                → chạy mỗi giờ (production)
 * ->withoutOverlapping()    → tránh chạy chồng chéo
 * ->onOneServer()           → chỉ 1 server chạy khi scale nhiều instance
 * ->appendOutputTo(...)     → ghi log
 */
Schedule::command('app:remind-abandoned-cart')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/**
 * ── 3. Gửi email xác nhận đơn hàng (nền) ──
 *
 * Chạy mỗi phút, quét đơn hàng đã tạo >= 5 phút và chưa gửi email
 * → Gửi email xác nhận qua SMTP (không chặn response đặt hàng)
 *
 * ->everyMinute()           → kiểm tra mỗi phút
 * ->withoutOverlapping()    → tránh gửi trùng
 */
Schedule::command('app:send-order-emails')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('court-bookings:clean-expired-locks')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('court-bookings:expire-pending --minutes=15')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('app:expire-flash-sales')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('court-bookings:mark-no-shows --grace=15')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command('oceanexpress:sync-orders')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/**
 * ── 5. Expire điểm thưởng hết hạn ──
 *
 * Chạy lúc 02:00 sáng mỗi ngày.
 * Quét loyalty_transactions type=earn đã quá expires_at → ghi expire transaction.
 */
Schedule::command('loyalty:expire-points')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/**
 * ── 6. Đồng bộ trạng thái GHN fallback ──
 *
 * Webhook là realtime path, command này là fallback polling khi webhook bị miss
 * hoặc môi trường dev chưa public backend bằng ngrok/Cloudflare Tunnel.
 */
Schedule::command('ghn:sync-status --limit=50')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/**
 * ── 7. Hủy đơn chưa thanh toán quá hạn & hoàn tồn kho ──
 *
 * Quét các đơn vnpay/bank_transfer ở trạng thái unpaid+pending quá 30 phút,
 * tự động hủy và hoàn trả tồn kho + coupon (chống giữ hàng vô thời hạn khi khách
 * bỏ dở thanh toán qua cổng redirect).
 */
Schedule::command('orders:cancel-expired-vnpay --minutes=30')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

/**
 * ── 8. Đồng bộ trạng thái Ocean Express fallback ──
 *
 * Webhook là realtime path; command này là lưới an toàn khi webhook bị miss
 * (hãng vận chuyển down, network lỗi, deploy trùng thời điểm). Không có nó thì
 * một webhook rớt = đơn hàng kẹt trạng thái vĩnh viễn.
 */
Schedule::command('ocean-express:sync-status --limit=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
