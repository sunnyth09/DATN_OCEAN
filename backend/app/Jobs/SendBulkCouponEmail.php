<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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

            if (!$emailUser || !$emailPass) {
                Log::warning('Coupon email: EMAIL_USER hoặc EMAIL_PASS chưa cấu hình.');
                return;
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

                            $emailMessage = (new \Symfony\Component\Mime\Email())
                                ->from($emailUser)
                                ->to($user->email)
                                ->subject('🎉 Mã giảm giá mới từ Ocean Store — ' . $this->coupon->code)
                                ->html($htmlBody);

                            $mailer->send($emailMessage);

                            // Tạo dữ liệu cho bảng notifications
                            $notificationData = [
                                'title'          => '🎁 Mã Giảm Giá Mới!',
                                'message'        => 'Bạn vừa nhận được mã giảm giá ' . $this->coupon->code . '. Nhanh tay sử dụng ngay!',
                                'coupon_code'    => $this->coupon->code,
                                'discount_value' => $this->coupon->value,
                                'type'           => 'coupon_received'
                            ];

                            $notifications[] = [
                                'id'              => \Illuminate\Support\Str::uuid(),
                                'type'            => 'App\Notifications\CouponReceivedNotification',
                                'notifiable_type' => \App\Models\User::class,
                                'notifiable_id'   => $user->user_id,
                                'data'            => json_encode($notificationData),
                                'read_at'         => null,
                                'created_at'      => \Carbon\Carbon::now(),
                                'updated_at'      => \Carbon\Carbon::now(),
                            ];

                            event(new \App\Events\UserNotificationEvent($user->user_id, $notificationData));
                        } catch (\Exception $e) {
                            Log::error("Coupon email failed for {$user->email}: " . $e->getMessage());
                        }
                    }

                    // Insert batch notifications into DB
                    if (!empty($notifications)) {
                        try {
                            \Illuminate\Support\Facades\DB::table('notifications')->insert($notifications);
                        } catch (\Exception $ex) {
                            Log::error("Save bulk notifications failed: " . $ex->getMessage());
                        }
                    }
                });

        } catch (\Exception $e) {
            Log::error('Coupon email system error: ' . $e->getMessage());
        }
    }

    /**
     * Build HTML email template thông báo mã giảm giá mới
     */
    private function buildCouponEmailHtml(Coupon $coupon, string $customerName): string
    {
        // Format giá trị giảm
        $valueText = match ($coupon->type) {
            'percent' => $coupon->value . '%',
            'free_ship' => number_format($coupon->value, 0, ',', '.') . 'đ (Freeship)',
            default => number_format($coupon->value, 0, ',', '.') . 'đ',
        };

        $typeLabel = match ($coupon->type) {
            'percent' => 'Giảm phần trăm',
            'free_ship' => 'Miễn phí vận chuyển',
            default => 'Giảm giá cố định',
        };

        // Thông tin thêm
        $extraInfo = '';
        if ($coupon->min_order_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Đơn tối thiểu</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . number_format($coupon->min_order_value, 0, ',', '.') . 'đ</td></tr>';
        }
        if ($coupon->type === 'percent' && $coupon->max_discount_value) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Giảm tối đa</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . number_format($coupon->max_discount_value, 0, ',', '.') . 'đ</td></tr>';
        }
        if ($coupon->end_date) {
            $extraInfo .= '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Hết hạn</td><td style="padding: 6px 0; color: #e53e3e; font-size: 13px; font-weight: 600; text-align: right;">' . date('d/m/Y H:i', strtotime($coupon->end_date)) . '</td></tr>';
        }

        $categoriesText = '';
        $coupon->load('categories');
        if ($coupon->categories->isNotEmpty()) {
            $catNames = $coupon->categories->pluck('name')->implode(', ');
            $categoriesText = '<tr><td style="padding: 6px 0; color: #6b7280; font-size: 13px;">Áp dụng danh mục</td><td style="padding: 6px 0; color: #1a1a2e; font-size: 13px; font-weight: 600; text-align: right;">' . htmlspecialchars($catNames) . '</td></tr>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin: 0; padding: 0; background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f0f2f5; padding: 40px 20px;">
                <tr><td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.08);">
                        <!-- Header -->
                        <tr><td style="background: linear-gradient(135deg, #0288d1 0%, #03a9f4 100%); padding: 28px 32px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 20px; margin: 0; font-weight: 600;">🎁 Mã Giảm Giá Mới!</h1>
                            <p style="color: rgba(255,255,255,0.85); font-size: 13px; margin: 6px 0 0;">Ocean Store gửi tặng bạn</p>
                        </td></tr>

                        <!-- Body -->
                        <tr><td style="padding: 32px 32px 24px;">
                            <p style="color: #1a1a2e; font-size: 15px; margin: 0 0 20px; line-height: 1.5;">Xin chào <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                            <p style="color: #6b7280; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Chúng tôi vừa tạo mã giảm giá đặc biệt dành cho bạn. Hãy sử dụng ngay nhé!</p>

                            <!-- Coupon Code Box -->
                            <div style="background: linear-gradient(135deg, #fff8e1 0%, #fff3e0 100%); border: 2px dashed #ff9800; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
                                <p style="color: #e65100; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px;">' . htmlspecialchars($typeLabel) . '</p>
                                <p style="color: #d84315; font-size: 32px; font-weight: 800; margin: 0 0 4px; font-family: \'Courier New\', monospace; letter-spacing: 3px;">' . htmlspecialchars($coupon->code) . '</p>
                                <p style="color: #2e7d32; font-size: 22px; font-weight: 700; margin: 8px 0 0;">Giảm ' . $valueText . '</p>
                            </div>

                            <!-- Info Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #e5e7eb; margin-bottom: 24px;">
                                ' . $extraInfo . $categoriesText . '
                            </table>

                            <!-- CTA -->
                            <div style="text-align: center;">
                                <a href="http://localhost:3000" style="display: inline-block; background: linear-gradient(135deg, #0288d1, #03a9f4); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 14px; font-weight: 700; letter-spacing: 0.5px;">Mua sắm ngay</a>
                            </div>
                        </td></tr>

                        <!-- Footer -->
                        <tr><td style="background: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
                            <p style="color: #9ca3af; font-size: 11px; margin: 0; text-align: center; line-height: 1.5;">
                                © ' . date('Y') . ' Ocean Fashion. All rights reserved.<br>
                                Email này được gửi tự động, vui lòng không trả lời.
                            </p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>';
    }
}
