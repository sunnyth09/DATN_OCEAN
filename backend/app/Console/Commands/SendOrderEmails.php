<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * =====================================================================
 * SendOrderEmails — Gửi email xác nhận đơn hàng (chạy nền, không đồng bộ)
 * =====================================================================
 *
 * CHẠY: php artisan app:send-order-emails
 * LỊCH: Mỗi phút (đăng ký trong routes/console.php)
 *
 * VẤN ĐỀ CŨ:
 *   Khi đặt hàng, email được gửi đồng bộ (synchronous) ngay trong request
 *   → SMTP mất 3-10 giây → response trả về rất chậm → UX xấu
 *
 * GIẢI PHÁP MỚI:
 *   1. Khi đặt hàng: chỉ tạo order, KHÔNG gửi email → response nhanh
 *   2. Cron job này chạy mỗi phút, quét đơn hàng:
 *      - email_sent = false (chưa gửi mail)
 *      - created_at >= 5 phút trước (đợi 5 phút để đảm bảo đơn hàng hợp lệ)
 *   3. Gửi email xác nhận cho từng đơn hàng
 *   4. Đánh dấu email_sent = true
 *
 * LỢI ÍCH:
 *   - Response đặt hàng nhanh hơn (giảm 3-10 giây)
 *   - Nếu SMTP lỗi → retry ở lần cron tiếp theo
 *   - User vẫn nhận email sau 5 phút (không ảnh hưởng trải nghiệm)
 */
class SendOrderEmails extends Command
{
    protected $signature = 'app:send-order-emails';
    protected $description = 'Gửi email xác nhận cho các đơn hàng mới (chạy nền sau 5 phút)';

    /**
     * Số phút chờ sau khi đặt hàng trước khi gửi email
     * (5 phút để đảm bảo đơn hàng đã ổn định, user không hủy ngay)
     */
    const DELAY_MINUTES = 1;

    public function handle(): int
    {
        // ─── Bước 1: Tìm đơn hàng cần gửi email ───
        // Điều kiện:
        // - email_sent = false → chưa gửi mail
        // - created_at <= 5 phút trước → đã đợi đủ thời gian
        // - fulfillment_status != 'cancelled' → đơn chưa bị hủy
        $pendingOrders = Order::where('email_sent', false)
            ->where('created_at', '<=', Carbon::now()->subMinutes(self::DELAY_MINUTES))
            ->where('fulfillment_status', '!=', 'cancelled')
            ->with(['items', 'user'])
            ->limit(20)  // Giới hạn mỗi lần chạy tối đa 20 đơn (tránh quá tải SMTP)
            ->get();

        if ($pendingOrders->isEmpty()) {
            $this->info('[' . now()->format('H:i:s') . '] Không có đơn hàng nào cần gửi email.');
            return 0;
        }

        $this->info("[" . now()->format('H:i:s') . "] Tìm thấy {$pendingOrders->count()} đơn hàng cần gửi email.");

        $successCount = 0;

        foreach ($pendingOrders as $order) {
            try {
                // Lấy user (có thể null nếu là khách vãng lai)
                $user = $order->user;

                // Email nhận xác nhận: ưu tiên email lưu trên đơn (guest nhập ở checkout),
                // fallback về email tài khoản (khách đăng nhập).
                $recipientEmail = $order->email ?: ($user->email ?? null);

                if (empty($recipientEmail)) {
                    $this->warn("  ⚠ Đơn {$order->order_code}: không có email, đánh dấu bỏ qua.");
                    $order->update(['email_sent' => true]); // Đánh dấu để không query lại
                    continue;
                }

                // ─── Bước 2: Gửi email qua SMTP ───
                $this->sendEmail($order, $recipientEmail);

                // --- Bước 2.1: Tạo thông báo in-app (chỉ cho khách đã đăng nhập) ---
                if ($user) {
                try {
                    $notificationData = [
                        'title'       => 'Đặt hàng thành công',
                        'message'     => 'Đơn hàng ' . $order->order_code . ' của bạn đã được ghi nhận.',
                        'order_code'  => $order->order_code,
                        'grand_total' => $order->grand_total,
                        'type'        => 'order_created'
                    ];

                    \Illuminate\Support\Facades\DB::table('notifications')->insert([
                        'id'              => \Illuminate\Support\Str::uuid(),
                        'type'            => 'App\Notifications\OrderCreatedNotification',
                        'notifiable_type' => \App\Models\User::class,
                        'notifiable_id'   => $user->user_id,
                        'data'            => json_encode($notificationData),
                        'read_at'         => null,
                        'created_at'      => \Carbon\Carbon::now(),
                        'updated_at'      => \Carbon\Carbon::now(),
                    ]);

                    // Broadcast realtime event
                    event(new \App\Events\UserNotificationEvent($user->user_id, $notificationData));
                } catch (\Exception $ex) {
                    Log::error("Save order notification failed for {$order->order_code}: " . $ex->getMessage());
                }
                } // end if ($user)

                // ─── Bước 3: Đánh dấu đã gửi ───
                $order->update(['email_sent' => true]);

                $this->info("  ✅ Đơn {$order->order_code} → {$recipientEmail}");
                $successCount++;

            } catch (\Exception $e) {
                // Lỗi gửi mail → KHÔNG đánh dấu email_sent = true
                // → Cron lần sau sẽ retry gửi lại
                $this->error("  ❌ Đơn {$order->order_code}: {$e->getMessage()}");
                Log::error("SendOrderEmails: Đơn {$order->order_code} failed: {$e->getMessage()}");
            }
        }

        $this->info("📧 Kết quả: {$successCount}/{$pendingOrders->count()} email gửi thành công.");
        return 0;
    }

    /**
     * Gửi email xác nhận đơn hàng
     *
     * Giữ nguyên template HTML gốc từ OrderController cũ,
     * nhưng dùng biến MAIL_* từ .env thay vì EMAIL_USER/EMAIL_PASS
     */
    private function sendEmail(Order $order, string $recipientEmail): void
    {
        $emailUser = env('MAIL_USERNAME', env('EMAIL_USER'));
        $emailPass = env('MAIL_PASSWORD', env('EMAIL_PASS'));

        if (!$emailUser || !$emailPass) {
            throw new \RuntimeException('MAIL_USERNAME hoặc MAIL_PASSWORD chưa được cấu hình trong .env');
        }

        // Tạo SMTP transport
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            'smtp.gmail.com',
            587,
            false
        );
        $transport->setUsername($emailUser);
        $transport->setPassword($emailPass);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        // Load items nếu chưa có
        $order->loadMissing('items');

        $actionUrl = $this->buildOrderActionUrl($order);
        $actionLabel = $order->user_id ? 'Xem lịch sử đơn hàng' : 'Theo dõi đơn hàng';

        // Build HTML table cho các sản phẩm
        $itemsHtml = '';
        foreach ($order->items as $item) {
            $variantInfo = $item->variant_name ? '(' . $item->color . '/' . $item->size . ')' : '';
            $itemsHtml .= '
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item->product_name) . ' ' . $variantInfo . ' x' . $item->quantity . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">' . number_format($item->line_total, 0, ',', '.') . 'đ</td>
            </tr>';
        }

        // Template email HTML (giữ nguyên thiết kế gốc)
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="background: #0288d1; padding: 20px; text-align: center; color: white;">
                    <h2 style="margin: 0;">Cảm ơn bạn đã đặt hàng!</h2>
                    <p style="margin: 5px 0 0;">Đơn hàng của bạn đã được ghi nhận</p>
                </div>
                <div style="padding: 20px;">
                    <p>Xin chào <strong>' . htmlspecialchars($order->recipient_name) . '</strong>,</p>
                    <p>Ocean Store xin thông báo đơn hàng <strong>' . $order->order_code . '</strong> của bạn đã được tạo thành công vào lúc ' . $order->created_at->format('H:i d/m/Y') . '.</p>
                    
                    <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 5px; color: #333;">Chi tiết đơn hàng</h3>
                    <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 20px;">
                        ' . $itemsHtml . '
                        <tr>
                            <td style="padding: 10px; text-align: right;">Tạm tính:</td>
                            <td style="padding: 10px; text-align: right;">' . number_format($order->subtotal, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right;">Phí vận chuyển:</td>
                            <td style="padding: 10px; text-align: right;">' . number_format($order->shipping_fee, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right;">Khuyến mãi:</td>
                            <td style="padding: 10px; text-align: right; color: green;">-' . number_format($order->discount_amount, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px;">TỔNG CỘNG:</td>
                            <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px; color: #e53e3e;">' . number_format($order->grand_total, 0, ',', '.') . 'đ</td>
                        </tr>
                    </table>

                    <h3 style="border-bottom: 2px solid #0288d1; padding-bottom: 5px; color: #333;">Thông tin giao hàng</h3>
                    <p><strong>Người nhận:</strong> ' . htmlspecialchars($order->recipient_name) . '</p>
                    <p><strong>Điện thoại:</strong> ' . htmlspecialchars($order->recipient_phone) . '</p>
                    <p><strong>Địa chỉ:</strong> ' . htmlspecialchars($order->shipping_address) . '</p>
                    <p><strong>Phương thức thanh toán:</strong> ' . strtoupper($order->payment_method) . '</p>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="background: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';

        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from($emailUser)
            ->to($recipientEmail)
            ->subject('Xác nhận đơn hàng ' . $order->order_code . ' - Ocean Store')
            ->html($htmlBody);

        $mailer->send($emailMessage);
    }

    private function buildOrderActionUrl(Order $order): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        if ($order->user_id) {
            return $frontendUrl . '/profile/orders';
        }

        $token = $this->ensureTrackingToken($order);
        return $token ? $frontendUrl . '/tracking/' . $token : $frontendUrl . '/tracking';
    }

    private function ensureTrackingToken(Order $order): ?string
    {
        if ($order->tracking_token) {
            return $order->tracking_token;
        }

        $order->tracking_token = hash('sha256', $order->order_code . Str::random(40) . microtime(true));
        $order->save();

        return $order->tracking_token;
    }
}
