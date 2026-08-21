<?php

namespace App\Console\Commands;

use App\Mail\CourtBookingCancelledMail;
use App\Mail\CourtBookingConfirmedMail;
use App\Mail\CourtBookingCreatedMail;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderShippingMail;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Notifications\BirthdayNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

class SendTestBatchEmail extends Command
{
    protected $signature = 'mail:test-batch {email=buichibinh2401@gmail.com}';

    protected $description = 'Gửi hàng loạt tất cả các mẫu email đã chuẩn hóa đến email kiểm thử';

    public function handle()
    {
        $targetEmail = $this->argument('email');
        $this->info("Bắt đầu gửi test hàng loạt email đến: {$targetEmail}");

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3302'), '/');

        // --- 1. Email Thanh Toán Thành Công (Online Payment) ---
        $this->line('1/12. Đang gửi email [Thanh Toán Thành Công]...');
        $htmlPayment = $this->buildPaymentSuccessHtml($frontendUrl);
        Mail::html($htmlPayment, function ($message) use ($targetEmail) {
            $message->to($targetEmail)->subject('[Ocean Sport] Xác nhận thanh toán thành công #ORD-ONLINE-8888');
        });
        $this->info('-> Đã gửi: 1. Thanh toán thành công');

        // --- 2. Email Xác Nhận Đơn Hàng (COD) ---
        $this->line('2/12. Đang gửi email [Xác Nhận Đơn Hàng COD]...');
        $htmlCod = $this->buildCodOrderHtml($frontendUrl);
        Mail::html($htmlCod, function ($message) use ($targetEmail) {
            $message->to($targetEmail)->subject('[Ocean Sport] Xác nhận đơn hàng #ORD-COD-9999');
        });
        $this->info('-> Đã gửi: 2. Xác nhận đơn hàng COD');

        // --- 3. Email OTP Đặt Lại Mật Khẩu ---
        $this->line('3/12. Đang gửi email [Mã OTP Đặt Lại Mật Khẩu]...');
        $htmlOtp = $this->buildOtpHtml();
        Mail::html($htmlOtp, function ($message) use ($targetEmail) {
            $message->to($targetEmail)->subject('[Ocean Sport] Mã OTP đặt lại mật khẩu của bạn');
        });
        $this->info('-> Đã gửi: 3. Mã OTP đặt lại mật khẩu');

        // --- 4. Email Tặng Mã Giảm Giá ---
        $this->line('4/12. Đang gửi email [Tặng Voucher / Mã Giảm Giá]...');
        $htmlCoupon = $this->buildCouponHtml($frontendUrl);
        Mail::html($htmlCoupon, function ($message) use ($targetEmail) {
            $message->to($targetEmail)->subject('[Ocean Sport] 🎁 Quà tặng mã giảm giá mới: VOUCHER100K');
        });
        $this->info('-> Đã gửi: 4. Tặng mã giảm giá');

        // --- 5. Email Phản Hồi Hỗ Trợ Khách Hàng ---
        $this->line('5/12. Đang gửi email [Phản Hồi CSKH]...');
        $htmlSupport = $this->buildSupportHtml();
        Mail::html($htmlSupport, function ($message) use ($targetEmail) {
            $message->to($targetEmail)->subject('[Ocean Sport] Phản hồi hỗ trợ: Tư vấn chọn vợt cầu lông cho người mới chơi');
        });
        $this->info('-> Đã gửi: 5. Phản hồi CSKH');

        // --- 6. Notification Chúc Mừng Sinh Nhật ---
        $this->line('6/12. Đang gửi email [Chúc Mừng Sinh Nhật]...');
        $fakeUser = new User([
            'full_name' => 'Bùi Chí Bình',
            'email' => $targetEmail,
        ]);
        $birthdayNotif = (new BirthdayNotification('BIRTHDAY-BUIBINH', '15%'))->toMail($fakeUser);
        $birthdayHtml = (string) $birthdayNotif->render();
        Mail::html($birthdayHtml, function ($message) use ($targetEmail, $birthdayNotif) {
            $message->to($targetEmail)->subject($birthdayNotif->subject);
        });
        $this->info('-> Đã gửi: 6. Chúc mừng sinh nhật');

        // --- 7. Notification Giỏ Hàng Bỏ Quên ---
        $this->line('7/12. Đang gửi email [Giỏ Hàng Bỏ Quên]...');
        $cartNotif = (new AbandonedCartNotification(3, 50))->toMail($fakeUser);
        $cartHtml = (string) $cartNotif->render();
        Mail::html($cartHtml, function ($message) use ($targetEmail, $cartNotif) {
            $message->to($targetEmail)->subject($cartNotif->subject);
        });
        $this->info('-> Đã gửi: 7. Giỏ hàng bỏ quên');

        // --- 8. Mail Vận Chuyển Đơn Hàng ---
        $this->line('8/12. Đang gửi email [Đơn Hàng Đang Vận Chuyển]...');
        $fakeOrder = new Order([
            'order_code' => 'ORD-SHIP-777',
            'order_id' => 123,
            'recipient_name' => 'Bùi Chí Bình',
            'tracking_number' => 'OE-789321456',
            'tracking_token' => 'test-tracking-token-ocean-sport',
            'total_amount' => 1250000,
        ]);
        $fakeOrder->setRelation('address', null);
        $shipMail = new OrderShippingMail($fakeOrder);
        Mail::html((string) $shipMail->render(), function ($message) use ($targetEmail, $shipMail) {
            $message->to($targetEmail)->subject($shipMail->envelope()->subject);
        });
        $this->info('-> Đã gửi: 8. Đơn hàng đang vận chuyển');

        // --- 9. Mail Hủy Đơn Hàng ---
        $this->line('9/12. Đang gửi email [Hủy Đơn Hàng]...');
        $fakeCancelOrder = new Order([
            'order_code' => 'ORD-CANCEL-555',
            'order_id' => 124,
            'created_at' => now()->subDay(),
            'cancel_reason' => 'Khách hàng thay đổi nhu cầu đặt mẫu khác',
            'total_amount' => 890000,
        ]);
        $fakeCancelOrder->setRelation('user', $fakeUser);
        $fakeCancelOrder->setRelation('items', collect([]));
        $cancelMail = new OrderCancelledMail($fakeCancelOrder, 'user', 'Khách hàng đổi nhu cầu');
        Mail::html((string) $cancelMail->render(), function ($message) use ($targetEmail, $cancelMail) {
            $message->to($targetEmail)->subject($cancelMail->envelope()->subject);
        });
        $this->info('-> Đã gửi: 9. Hủy đơn hàng');

        // --- 10. Mail Đặt Sân Cầu Lông (Tạo yêu cầu) ---
        $this->line('10/12. Đang gửi email [Đặt Sân Cầu Lông - Đã Tạo]...');
        $fakeBooking = new CourtBooking([
            'booking_code' => 'CB-2026-8888',
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'total_amount' => 300000,
        ]);
        $fakeCourt = new Court(['court_name' => 'Sân số 01 (VIP Pro)']);
        $fakeBooking->setRelation('court', $fakeCourt);
        $fakeBooking->setRelation('user', $fakeUser);
        $fakeBooking->setRelation('services', collect([]));
        $bookingCreatedMail = new CourtBookingCreatedMail($fakeBooking);
        Mail::html((string) $bookingCreatedMail->render(), function ($message) use ($targetEmail, $bookingCreatedMail) {
            $message->to($targetEmail)->subject($bookingCreatedMail->envelope()->subject);
        });
        $this->info('-> Đã gửi: 10. Đặt sân - Đã tạo');

        // --- 11. Mail Xác Nhận Lịch Đặt Sân (Kèm QR Check-in) ---
        $this->line('11/12. Đang gửi email [Đặt Sân Cầu Lông - Đã Xác Nhận]...');
        $bookingConfirmedMail = new CourtBookingConfirmedMail($fakeBooking);
        Mail::html((string) $bookingConfirmedMail->render(), function ($message) use ($targetEmail, $bookingConfirmedMail) {
            $message->to($targetEmail)->subject($bookingConfirmedMail->envelope()->subject);
        });
        $this->info('-> Đã gửi: 11. Đặt sân - Đã xác nhận');

        // --- 12. Mail Hủy Lịch Đặt Sân ---
        $this->line('12/12. Đang gửi email [Đặt Sân Cầu Lông - Đã Hủy]...');
        $fakeBooking->cancel_reason = 'Khách có lịch bận đột xuất';
        $bookingCancelledMail = new CourtBookingCancelledMail($fakeBooking, 300000, 'user');
        Mail::html((string) $bookingCancelledMail->render(), function ($message) use ($targetEmail, $bookingCancelledMail) {
            $message->to($targetEmail)->subject($bookingCancelledMail->envelope()->subject);
        });
        $this->info('-> Đã gửi: 12. Đặt sân - Đã hủy');

        $this->newLine();
        $this->info("🎉 HOÀN TẤT! Toàn bộ 12 email kiểm thử đã được gửi thành công đến {$targetEmail}.");

        return 0;
    }

    private function buildPaymentSuccessHtml(string $frontendUrl): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Xác nhận thanh toán đơn hàng #ORD-ONLINE-8888</title>
        </head>
        <body style="font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 30px 15px; color: #2d3436; -webkit-font-smoothing: antialiased;">
            <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                <div style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center; color: #ffffff;">
                    <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                    <h1 style="margin: 0; font-size: 24px; font-weight: 800; line-height: 1.3; color: #ffffff;">Thanh toán thành công!</h1>
                    <p style="margin: 8px 0 0; font-size: 14px; color: #ffd9de; font-weight: 500;">Đơn hàng <strong>#ORD-ONLINE-8888</strong> đã được xác nhận thanh toán</p>
                </div>
                <div style="padding: 28px 24px;">
                    <p style="font-size: 15px; line-height: 1.6; margin-top: 0; color: #334155;">
                        Xin chào <strong style="color: #0f172a;">Bùi Chí Bình</strong>,<br>
                        Ocean Sport xin cảm ơn bạn đã mua sắm! Đơn hàng của bạn đã được thanh toán thành công vào lúc <strong>'.now()->format('H:i d/m/Y').'</strong> và đang được nhân viên chuẩn bị đóng gói chuyển đi.
                    </p>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 14px 18px; margin: 20px 0;">
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width: 32px; vertical-align: middle;">
                                    <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; background: #10b981; color: white; border-radius: 50%; font-size: 14px; font-weight: bold;">✓</span>
                                </td>
                                <td style="vertical-align: middle; padding-left: 8px;">
                                    <div style="font-size: 14px; font-weight: 700; color: #065f46;">ĐÃ THANH TOÁN THÀNH CÔNG</div>
                                    <div style="font-size: 13px; color: #047857; margin-top: 2px;">Cổng thanh toán: <strong>Chuyển khoản SePay QR Auto</strong></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 24px;">
                        <div style="font-size: 15px; font-weight: 700; color: #1e293b; border-left: 4px solid #E63B6F; padding-left: 10px; margin-bottom: 12px;">Chi tiết sản phẩm</div>
                        <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <tr style="background: #FFF0F3;">
                                <th style="padding: 10px 14px; text-align: left; font-size: 13px; font-weight: 700; color: #b50c4d;">Sản phẩm</th>
                                <th style="padding: 10px 14px; text-align: right; font-size: 13px; font-weight: 700; color: #b50c4d;">Thành tiền</th>
                            </tr>
                            <tr>
                                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px;">
                                    <div style="font-weight: 600; color: #1e293b;">Vợt Cầu Lông Yonex Astrox 100ZZ Pro</div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">(Kurenai/4UG5) <span style="display:inline-block; margin-left: 6px; padding: 1px 6px; background: #f1f5f9; border-radius: 4px; font-weight: 600;">x1</span></div>
                                </td>
                                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 700; color: #0f172a; font-size: 14px;">
                                    4.250.000đ
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px;">
                                    <div style="font-weight: 600; color: #1e293b;">Giày Cầu Lông Yonex Power Cushion 65Z3</div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">(Trắng Đỏ/Size 42) <span style="display:inline-block; margin-left: 6px; padding: 1px 6px; background: #f1f5f9; border-radius: 4px; font-weight: 600;">x1</span></div>
                                </td>
                                <td style="padding: 12px 14px; border-bottom: 1px solid #f1f5f9; text-align: right; font-weight: 700; color: #0f172a; font-size: 14px;">
                                    2.650.000đ
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin-top: 20px; background: #f8fafc; border-radius: 10px; padding: 16px; border: 1px solid #e2e8f0;">
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 14px;">Tạm tính:</td>
                                <td style="padding: 4px 0; text-align: right; color: #334155; font-size: 14px; font-weight: 600;">6.900.000đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 14px;">Giảm giá Voucher:</td>
                                <td style="padding: 4px 0; text-align: right; color: #16a34a; font-size: 14px; font-weight: 600;">-200.000đ</td>
                            </tr>
                            <tr>
                                <td style="padding: 4px 0; color: #64748b; font-size: 14px;">Phí vận chuyển:</td>
                                <td style="padding: 4px 0; text-align: right; color: #334155; font-size: 14px; font-weight: 600;">Miễn phí</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 6px;"></td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; color: #0f172a; font-size: 16px; font-weight: 800;">Tổng thanh toán:</td>
                                <td style="padding: 6px 0; text-align: right; color: #E63B6F; font-size: 20px; font-weight: 800;">6.700.000đ</td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin: 32px 0 24px; text-align: center;">
                        <a href="'.$frontendUrl.'/profile/orders/123" style="background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-weight: 700; font-size: 15px; display: inline-block; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);">Xem chi tiết đơn hàng ➔</a>
                    </div>
                </div>
                <div style="background: #f8fafc; padding: 20px 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                    <div style="font-weight: 700; color: #64748b; font-size: 12px; margin-bottom: 4px;">OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP</div>
                    <div style="font-size: 11px; color: #94a3b8; line-height: 1.5;">
                        Hotline: <strong style="color: #E63B6F;">1900 6868</strong> | Email: <strong style="color: #E63B6F;">contact@oceansport.vn</strong><br>
                        © '.date('Y').' Ocean Sport. All rights reserved.
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    private function buildCodOrderHtml(string $frontendUrl): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Xác nhận đơn hàng #ORD-COD-9999</title>
        </head>
        <body style="font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 30px 15px; color: #2d3436; -webkit-font-smoothing: antialiased;">
            <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                <div style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center; color: #ffffff;">
                    <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                    <h1 style="margin: 0; font-size: 24px; font-weight: 800; line-height: 1.3; color: #ffffff;">Đặt hàng thành công!</h1>
                    <p style="margin: 8px 0 0; font-size: 14px; color: #ffd9de; font-weight: 500;">Mã đơn hàng: <strong>#ORD-COD-9999</strong></p>
                </div>
                <div style="padding: 28px 24px;">
                    <p style="font-size: 15px; line-height: 1.6; margin-top: 0; color: #334155;">
                        Xin chào <strong style="color: #0f172a;">Bùi Chí Bình</strong>,<br>
                        Cảm ơn bạn đã đặt hàng tại Ocean Sport! Chúng tôi đã nhận được đơn hàng của bạn và đang tiến hành xử lý đóng gói.
                    </p>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 14px 18px; margin: 20px 0;">
                        <table width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width: 32px; vertical-align: middle;">
                                    <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; background: #3b82f6; color: white; border-radius: 50%; font-size: 14px; font-weight: bold;">📦</span>
                                </td>
                                <td style="vertical-align: middle; padding-left: 8px;">
                                    <div style="font-size: 14px; font-weight: 700; color: #1e40af;">HÌNH THỨC THANH TOÁN: COD</div>
                                    <div style="font-size: 13px; color: #2563eb; margin-top: 2px;">Thanh toán khi nhận hàng: <strong>1.450.000đ</strong></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div style="margin: 32px 0 24px; text-align: center;">
                        <a href="'.$frontendUrl.'/tracking/sample-token" style="background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-weight: 700; font-size: 15px; display: inline-block; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);">Theo dõi hành trình đơn hàng ➔</a>
                    </div>
                </div>
                <div style="background: #f8fafc; padding: 20px 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                    <div style="font-weight: 700; color: #64748b; font-size: 12px; margin-bottom: 4px;">OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP</div>
                    <div style="font-size: 11px; color: #94a3b8; line-height: 1.5;">
                        Hotline: <strong style="color: #E63B6F;">1900 6868</strong> | Email: <strong style="color: #E63B6F;">contact@oceansport.vn</strong><br>
                        © '.date('Y').' Ocean Sport. All rights reserved.
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }

    private function buildOtpHtml(): string
    {
        $otpDigits = ['8', '2', '6', '1', '9', '4'];
        $otpBoxes = '';
        foreach ($otpDigits as $digit) {
            $otpBoxes .= '<td style="padding: 0 4px;"><div style="width: 48px; height: 56px; background: #FFF0F3; border: 2px solid #E63B6F; border-radius: 10px; font-size: 26px; font-weight: 800; color: #b50c4d; line-height: 56px; text-align: center; font-family: \'Courier New\', monospace;">'.$digit.'</div></td>';
        }

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Mã OTP đặt lại mật khẩu</title>
        </head>
        <body style="margin: 0; padding: 30px 15px; background: #f8f9fa; font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td align="center">
                    <table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                        <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center;">
                            <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                            <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 800; letter-spacing: 0.5px;">Mã OTP Đặt Lại Mật Khẩu</h1>
                            <p style="color: #ffd9de; font-size: 13px; margin: 6px 0 0;">Bảo mật thông tin tài khoản của bạn</p>
                        </td></tr>
                        <tr><td style="padding: 32px 28px 24px;">
                            <p style="color: #1e293b; font-size: 15px; margin: 0 0 8px; line-height: 1.5;">Xin chào <strong style="color: #0f172a;">Bùi Chí Bình</strong>,</p>
                            <p style="color: #64748b; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng nhập mã xác thực OTP 6 số bên dưới để tiếp tục:</p>
                            <table cellpadding="0" cellspacing="0" style="margin: 0 auto 24px;">
                                <tr>'.$otpBoxes.'</tr>
                            </table>
                            <div style="background: #FFF0F3; border: 1px solid #ffd9de; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;">
                                <p style="color: #b50c4d; font-size: 13px; margin: 0; text-align: center; line-height: 1.5; font-weight: 600;">
                                    ⏰ Mã có hiệu lực trong <strong>15 phút</strong>. Tuyệt đối không chia sẻ mã này với bất kỳ ai!
                                </p>
                            </div>
                        </td></tr>
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

    private function buildCouponHtml(string $frontendUrl): string
    {
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
                        <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center;">
                            <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                            <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 800;">🎁 Quà Tặng Mã Giảm Giá!</h1>
                            <p style="color: #ffd9de; font-size: 13px; margin: 6px 0 0;">Ocean Sport trân trọng gửi tặng bạn ưu đãi đặc biệt</p>
                        </td></tr>
                        <tr><td style="padding: 32px 28px 24px;">
                            <p style="color: #1e293b; font-size: 15px; margin: 0 0 16px; line-height: 1.5;">Xin chào <strong style="color: #0f172a;">Bùi Chí Bình</strong>,</p>
                            <p style="color: #64748b; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Ocean Sport gửi tặng bạn voucher ưu đãi để bạn thỏa sức mua sắm các sản phẩm thể thao yêu thích:</p>
                            <div style="background: #FFF0F3; border: 2px dashed #E63B6F; border-radius: 14px; padding: 24px; text-align: center; margin-bottom: 24px;">
                                <p style="color: #b50c4d; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px;">GIẢM GIÁ TIỀN MẶT</p>
                                <p style="color: #E63B6F; font-size: 32px; font-weight: 800; margin: 0 0 4px; font-family: \'Courier New\', monospace; letter-spacing: 3px;">VOUCHER100K</p>
                                <p style="color: #16a34a; font-size: 20px; font-weight: 800; margin: 8px 0 0;">Giảm 100.000đ</p>
                            </div>
                            <div style="text-align: center;">
                                <a href="'.$frontendUrl.'/coupon" style="display: inline-block; background: linear-gradient(135deg, #E63B6F 0%, #C4305D 100%); color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 10px; font-size: 15px; font-weight: 700; box-shadow: 0 4px 14px rgba(230, 59, 111, 0.35);">Khám phá & Mua sắm ngay ➔</a>
                            </div>
                        </td></tr>
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

    private function buildSupportHtml(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Phản hồi hỗ trợ từ Ocean Sport</title>
        </head>
        <body style="margin: 0; padding: 30px 15px; background: #f8f9fa; font-family: \'Plus Jakarta Sans\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(230, 59, 111, 0.08); border: 1px solid #f1f3f5;">
                        <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center;">
                            <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                            <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 800;">Phản Hồi Yêu Cầu Hỗ Trợ</h1>
                            <p style="color: #ffd9de; font-size: 13px; margin: 6px 0 0;">Bộ phận Chăm sóc Khách hàng Ocean Sport</p>
                        </td></tr>
                        <tr><td style="padding: 32px 28px 24px;">
                            <p style="color: #1e293b; font-size: 15px; margin: 0 0 8px; line-height: 1.5;">Xin chào <strong style="color: #0f172a;">Bùi Chí Bình</strong>,</p>
                            <p style="color: #64748b; font-size: 14px; margin: 0 0 20px; line-height: 1.6;">Cảm ơn bạn đã liên hệ với chúng tôi về chủ đề: <em>"Tư vấn chọn vợt cầu lông cho người mới chơi"</em>. Dưới đây là phản hồi từ đội ngũ kỹ thuật viên:</p>
                            <div style="background: #FFF0F3; border-left: 4px solid #E63B6F; padding: 18px 20px; border-radius: 10px; margin: 0 0 24px;">
                                <p style="color: #1e293b; margin: 0; white-space: pre-wrap; font-size: 14px; line-height: 1.6;">Dạ chào anh Bình, với người mới chơi cần sự linh hoạt và dễ thuần, bên em khuyến nghị anh nên chọn dòng vợt thân dẻo hoặc trung bình, đầu vợt cân bằng (như dòng Yonex Nanoflare 001 hoặc Lining Windstorm 72) với mức căng cước khoảng 9.5kg - 10kg để tránh chấn thương cổ tay anh nhé!</p>
                            </div>
                            <p style="color: #64748b; font-size: 13px; margin: 0; line-height: 1.5;">
                                Nếu bạn cần thêm bất kỳ thông tin nào, hãy phản hồi trực tiếp email này hoặc liên hệ hotline <strong style="color: #E63B6F;">1900 6868</strong> để được hỗ trợ tức thì.
                            </p>
                        </td></tr>
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
