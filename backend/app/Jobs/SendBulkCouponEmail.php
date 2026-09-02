<?php

namespace App\Jobs;

use App\Events\UserNotificationEvent;
use App\Models\Coupon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class SendBulkCouponEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Coupon $coupon;

    /**
     * Create a new job instance.
     */
    public function __construct(Coupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $emailUser = config('services.email.username');
            $emailPass = config('services.email.password');

            if (! $emailUser || ! $emailPass) {
                Log::warning('Coupon email: EMAIL_USER hoặc EMAIL_PASS chưa cấu hình.');

                return;
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

            // Dùng chunk để giải phóng bộ nhớ thay vì get() toàn bộ User
            User::whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('deleted_at')
                ->select('user_id', 'email', 'full_name')
                ->chunk(200, function ($users) use ($mailer, $emailUser) {
                    $notifications = [];
                    foreach ($users as $user) {
                        try {
                            $htmlBody = $this->buildCouponEmailHtml($this->coupon, $user->full_name ?? 'Quý khách');

                            $emailMessage = (new Email)
                                ->from($emailUser)
                                ->to($user->email)
                                ->subject('[Ocean Sport] 🎁 Quà tặng mã giảm giá mới: '.$this->coupon->code)
                                ->html($htmlBody);

                            $mailer->send($emailMessage);

                            // Tạo dữ liệu cho bảng notifications
                            $notificationData = [
                                'title' => '🎁 Mã Giảm Giá Mới!',
                                'message' => 'Bạn vừa nhận được mã giảm giá '.$this->coupon->code.'. Nhanh tay sử dụng ngay!',
                                'coupon_code' => $this->coupon->code,
                                'discount_value' => $this->coupon->value,
                                'type' => 'coupon_received',
                            ];

                            $notifications[] = [
                                'id' => Str::uuid(),
                                'type' => 'App\Notifications\CouponReceivedNotification',
                                'notifiable_type' => User::class,
                                'notifiable_id' => $user->user_id,
                                'data' => json_encode($notificationData),
                                'read_at' => null,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ];

                            event(new UserNotificationEvent($user->user_id, $notificationData));
                        } catch (\Exception $e) {
                            Log::error("Coupon email failed for {$user->email}: ".$e->getMessage());
                        }
                    }

                    // Insert batch notifications into DB
                    if (! empty($notifications)) {
                        try {
                            DB::table('notifications')->insert($notifications);
                        } catch (\Exception $ex) {
                            Log::error('Save bulk notifications failed: '.$ex->getMessage());
                        }
                    }
                });

        } catch (\Exception $e) {
            Log::error('Coupon email system error: '.$e->getMessage());
        }
    }

    /**
     * Build HTML email template thông báo mã giảm giá mới
     */
    private function buildCouponEmailHtml(Coupon $coupon, string $customerName): string
    {
        // Format giá trị giảm
        $valueText = match ($coupon->type) {
            'percent' => $coupon->value.'%',
            'free_ship' => number_format($coupon->value, 0, ',', '.').'đ (Freeship)',
            default => number_format($coupon->value, 0, ',', '.').'đ',
        };

        $typeLabel = match ($coupon->type) {
            'percent' => 'Giảm phần trăm',
            'free_ship' => 'Miễn phí vận chuyển',
            default => 'Giảm giá cố định',
        };

        // Thông tin thêm
        $extraInfo = '';
        if ($coupon->min_order_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Đơn tối thiểu</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">'.number_format($coupon->min_order_value, 0, ',', '.').'đ</td></tr>';
        }
        if ($coupon->type === 'percent' && $coupon->max_discount_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Giảm tối đa</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">'.number_format($coupon->max_discount_value, 0, ',', '.').'đ</td></tr>';
        }
        if ($coupon->end_date) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Hết hạn</td><td style="padding: 6px 0; color: #e53e3e; font-size: 13px; font-weight: 600; text-align: right;">'.date('d/m/Y H:i', strtotime($coupon->end_date)).'</td></tr>';
        }

        $categoriesText = '';
        $coupon->load('categories');
        if ($coupon->categories->isNotEmpty()) {
            $catNames = $coupon->categories->pluck('name')->implode(', ');
            $categoriesText = '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Áp dụng danh mục</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">'.htmlspecialchars($catNames).'</td></tr>';
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Mã giảm giá mới dành cho bạn</title>
        </head>
        <body style="margin: 0; padding: 30px 15px; background: #f8f9fa; font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                        <!-- Header -->
                        <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center;">
                            <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                            <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 800;">🎁 Quà Tặng Mã Giảm Giá!</h1>
                            <p style="color: #ffd9de; font-size: 13px; margin: 6px 0 0;">Ocean Sport trân trọng gửi tặng bạn ưu đãi đặc biệt</p>
                        </td></tr>

                        <!-- Body -->
                        <tr><td style="padding: 32px 28px 24px;">
                            <p style="color: #1e293b; font-size: 15px; margin: 0 0 16px; line-height: 1.5;">Xin chào <strong style="color: #0f172a;">'.htmlspecialchars($customerName).'</strong>,</p>
                            <p style="color: #64748b; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Ocean Sport gửi tặng bạn voucher ưu đãi để bạn thỏa sức mua sắm các sản phẩm thể thao yêu thích:</p>

                            <!-- Coupon Code Box -->
                            <div style="background: #FFF0F3; border: 2px dashed #E63B6F; border-radius: 14px; padding: 24px; text-align: center; margin-bottom: 24px;">
                                <p style="color: #b50c4d; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px;">'.htmlspecialchars($typeLabel).'</p>
                                <p style="color: #E63B6F; font-size: 32px; font-weight: 800; margin: 0 0 4px; font-family: \'Courier New\', monospace; letter-spacing: 3px;">'.htmlspecialchars($coupon->code).'</p>
                                <p style="color: #16a34a; font-size: 20px; font-weight: 800; margin: 8px 0 0;">Giảm '.$valueText.'</p>
                            </div>

                            <!-- Info Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #f1f5f9; margin-bottom: 24px; font-size: 13px;">
                                '.$extraInfo.$categoriesText.'
                            </table>

                            <!-- CTA -->
                            <div style="text-align: center;">
                                <a href="'.$frontendUrl.'/coupon" style="display: inline-block; background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 15px; font-weight: 700; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);">Khám phá & Mua sắm ngay ➔</a>
                            </div>
                        </td></tr>

                        <!-- Footer -->
                        <tr><td style="background: #f8fafc; padding: 20px 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <div style="font-weight: 700; color: #64748b; font-size: 12px; margin-bottom: 4px;">OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP</div>
                            <div style="font-size: 11px; color: #94a3b8; line-height: 1.5;">
                                Hotline: <strong style="color: #E63B6F;">1900 6868</strong> | Email: <strong style="color: #E63B6F;">contact@oceansport.vn</strong><br>
                                © '.date('Y').' Ocean Sport. All rights reserved.
                            </div>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>';
    }
}
