<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

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
            $this->info('['.now()->format('H:i:s').'] Không có đơn hàng nào cần gửi email.');

            return 0;
        }

        $this->info('['.now()->format('H:i:s')."] Tìm thấy {$pendingOrders->count()} đơn hàng cần gửi email.");

        $successCount = 0;

        foreach ($pendingOrders as $order) {
            try {
                // Nếu đơn hàng đã được thanh toán online (SePay/VNPay/Wallet), đánh dấu đã xử lý để không gửi đúp
                if ($order->payment_status === 'paid') {
                    $this->info("  ℹ Đơn {$order->order_code} đã thanh toán online, đánh dấu hoàn tất.");
                    $order->update(['email_sent' => true]);

                    continue;
                }

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

                // ─── Bước 3: Đánh dấu đã gửi ───
                $order->update(['email_sent' => true]);

                $this->info("  ✅ Đơn {$order->order_code} → {$recipientEmail}");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("  ❌ Đơn {$order->order_code}: {$e->getMessage()}");
                Log::error("SendOrderEmails: Đơn {$order->order_code} failed: {$e->getMessage()}");
            }
        }

        $this->info("📧 Kết quả: {$successCount}/{$pendingOrders->count()} email gửi thành công.");

        return 0;
    }

    /**
     * Gửi email xác nhận đơn hàng với giao diện chuẩn Rose Pink (#E63B6F)
     */
    private function sendEmail(Order $order, string $recipientEmail): void
    {
        $emailUser = config('mail.mailers.smtp.username') ?: config('services.email.username');
        $emailPass = config('mail.mailers.smtp.password') ?: config('services.email.password');

        if (! $emailUser || ! $emailPass) {
            throw new \RuntimeException('MAIL_USERNAME hoặc MAIL_PASSWORD chưa được cấu hình trong .env');
        }

        // Tạo SMTP transport
        $transport = new EsmtpTransport(
            'smtp.gmail.com',
            587,
            false
        );
        $transport->setUsername($emailUser);
        $transport->setPassword($emailPass);
        $mailer = new Mailer($transport);

        // Load items nếu chưa có
        $order->loadMissing('items');

        $methodLabel = match ($order->payment_method) {
            'cod' => 'Thanh toán khi nhận hàng (COD)',
            'bank_transfer' => 'Chuyển khoản ngân hàng (SePay)',
            'vnpay' => 'VNPay',
            'momo' => 'Ví MoMo',
            'wallet' => 'Ví Ocean',
            default => strtoupper($order->payment_method ?? 'COD'),
        };

        $actionUrl = $this->buildOrderActionUrl($order);
        $actionLabel = $order->user_id ? 'Xem chi tiết đơn hàng ➔' : 'Theo dõi đơn hàng ➔';

        // Build HTML table cho các sản phẩm
        $itemsHtml = '';
        foreach ($order->items as $item) {
            $variantInfo = $item->variant_name ? ' ('.htmlspecialchars($item->color ?? '').'/'.htmlspecialchars($item->size ?? '').')' : '';
            $itemsHtml .= '
            <tr>
                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px;">
                    <div style="font-weight: 600; color: #1e293b;">'.htmlspecialchars($item->product_name).'</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">'.$variantInfo.' <span style="display:inline-block; margin-left: 6px; padding: 1px 6px; background: #f1f5f9; border-radius: 4px; font-weight: 600;">x'.$item->quantity.'</span></div>
                </td>
                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 700; color: #0f172a; font-size: 14px; white-space: nowrap;">
                    '.number_format($item->line_total, 0, ',', '.').'đ
                </td>
            </tr>';
        }

        $createdAtFormatted = $order->created_at ? $order->created_at->format('H:i d/m/Y') : now()->format('H:i d/m/Y');

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Xác nhận đơn hàng '.$order->order_code.'</title>
        </head>
        <body style="font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 30px 15px; color: #2d3436; -webkit-font-smoothing: antialiased;">
            <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                
                <!-- HEADER WITH ROSE PINK BRAND GRADIENT -->
                <div style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center; color: #ffffff;">
                    <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                    <h1 style="margin: 0; font-size: 24px; font-weight: 800; line-height: 1.3; color: #ffffff;">Cảm ơn bạn đã đặt hàng!</h1>
                    <p style="margin: 8px 0 0; font-size: 14px; color: #ffd9de; font-weight: 500;">Đơn hàng <strong>#'.$order->order_code.'</strong> đã được ghi nhận thành công</p>
                </div>

                <!-- MAIN CONTENT -->
                <div style="padding: 28px 24px;">
                    <p style="font-size: 15px; line-height: 1.6; margin-top: 0; color: #334155;">
                        Xin chào <strong style="color: #0f172a;">'.htmlspecialchars($order->recipient_name).'</strong>,<br>
                        Ocean Sport xin thông báo đơn hàng của bạn đã được tiếp nhận vào lúc <strong>'.$createdAtFormatted.'</strong>. Chúng tôi sẽ nhanh chóng xử lý và bàn giao cho đơn vị vận chuyển sớm nhất.
                    </p>

                    <!-- PAYMENT STATUS BADGE -->
                    <div style="background: #FFF0F3; border: 1px solid #ffd9de; border-radius: 12px; padding: 14px 18px; margin: 20px 0;">
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width: 32px; vertical-align: middle;">
                                    <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; background: #E63B6F; color: white; border-radius: 50%; font-size: 13px; font-weight: bold;">📦</span>
                                </td>
                                <td style="vertical-align: middle; padding-left: 8px;">
                                    <div style="font-size: 14px; font-weight: 700; color: #b50c4d;">PHƯƠNG THỨC THANH TOÁN</div>
                                    <div style="font-size: 13px; color: #334155; margin-top: 2px;">'.$methodLabel.'</div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- ORDER ITEMS TABLE -->
                    <div style="margin-top: 24px;">
                        <div style="font-size: 15px; font-weight: 700; color: #1e293b; border-left: 4px solid #E63B6F; padding-left: 10px; margin-bottom: 12px;">Chi tiết sản phẩm</div>
                        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <thead>
                                <tr style="background: #FFF0F3;">
                                    <th style="padding: 10px 14px; text-align: left; font-size: 12px; font-weight: 700; color: #b50c4d; text-transform: uppercase;">Sản phẩm</th>
                                    <th style="padding: 10px 14px; text-align: right; font-size: 12px; font-weight: 700; color: #b50c4d; text-transform: uppercase;">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                '.$itemsHtml.'
                            </tbody>
                        </table>
                    </div>

                    <!-- ORDER SUMMARY -->
                    <div style="margin-top: 16px; background: #fafafa; border: 1px solid #f1f3f5; border-radius: 10px; padding: 14px 16px;">
                        <table width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px;">
                            <tr>
                                <td style="padding: 5px 0; color: #64748b;">Tạm tính:</td>
                                <td style="padding: 5px 0; text-align: right; font-weight: 600; color: #334155;">'.number_format($order->subtotal, 0, ',', '.').'đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; color: #64748b;">Phí vận chuyển:</td>
                                <td style="padding: 5px 0; text-align: right; font-weight: 600; color: #334155;">'.number_format($order->shipping_fee, 0, ',', '.').'đ</td>
                            </tr>';

        if ($order->discount_amount > 0) {
            $htmlBody .= '
                            <tr>
                                <td style="padding: 5px 0; color: #64748b;">Khuyến mãi / Giảm giá:</td>
                                <td style="padding: 5px 0; text-align: right; font-weight: 700; color: #16a34a;">-'.number_format($order->discount_amount, 0, ',', '.').'đ</td>
                            </tr>';
        }

        $htmlBody .= '
                            <tr>
                                <td colspan="2" style="padding-top: 8px; border-top: 1px dashed #e2e8f0;"></td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-size: 16px; font-weight: 800; color: #0f172a;">TỔNG CỘNG:</td>
                                <td style="padding: 6px 0; text-align: right; font-size: 18px; font-weight: 800; color: #E63B6F;">'.number_format($order->grand_total, 0, ',', '.').'đ</td>
                            </tr>
                        </table>
                    </div>

                    <!-- SHIPPING INFO -->
                    <div style="margin-top: 22px; background: #FFF0F3; border: 1px solid #ffd9de; border-radius: 12px; padding: 16px 18px;">
                        <div style="font-size: 14px; font-weight: 700; color: #b50c4d; margin-bottom: 8px;">Địa chỉ nhận hàng</div>
                        <div style="font-size: 14px; color: #334155; line-height: 1.5;">
                            <div><strong>Người nhận:</strong> '.htmlspecialchars($order->recipient_name).' - '.htmlspecialchars($order->recipient_phone).'</div>
                            <div style="margin-top: 4px;"><strong>Địa chỉ:</strong> '.htmlspecialchars($order->shipping_address).'</div>
                        </div>
                    </div>

                    <!-- CTA BUTTON -->
                    <div style="text-align: center; margin: 32px 0 12px;">
                        <a href="'.htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8').'" style="display: inline-block; background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%); color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35); letter-spacing: 0.3px;">
                            '.htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8').'
                        </a>
                    </div>
                </div>

                <!-- FOOTER -->
                <div style="background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; line-height: 1.6;">
                    <div style="font-weight: 700; color: #64748b; margin-bottom: 4px;">OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP</div>
                    <div>Hotline hỗ trợ: <strong style="color: #E63B6F;">1900 6868</strong> | Email: <strong style="color: #E63B6F;">contact@oceansport.vn</strong></div>
                    <div style="margin-top: 6px;">Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với chúng tôi để được giải đáp nhanh chóng nhất.</div>
                </div>

            </div>
        </body>
        </html>
        ';

        $emailMessage = (new Email)
            ->from($emailUser)
            ->to($recipientEmail)
            ->subject('[Ocean Sport] Xác nhận đặt hàng thành công — #'.$order->order_code)
            ->html($htmlBody);

        $mailer->send($emailMessage);
    }

    private function buildOrderActionUrl(Order $order): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        if ($order->user_id) {
            return $frontendUrl.'/profile/orders/'.$order->order_id;
        }

        $token = $this->ensureTrackingToken($order);

        return $token ? $frontendUrl.'/tracking/'.$token : $frontendUrl.'/tracking';
    }

    private function ensureTrackingToken(Order $order): ?string
    {
        if ($order->tracking_token) {
            return $order->tracking_token;
        }

        $order->tracking_token = hash('sha256', $order->order_code.Str::random(40).microtime(true));
        $order->save();

        return $order->tracking_token;
    }
}
