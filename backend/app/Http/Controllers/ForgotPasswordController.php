<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class ForgotPasswordController extends Controller
{
    /**
     * Bước 1: Gửi mã OTP 6 số qua email
     * OTP có hiệu lực 15 phút
     */
    public function sendOtp(Request $request)
    {
        $email = $request->input('email');

        if (! $email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng nhập địa chỉ email!',
            ], 422);
        }

        // Kiểm tra email có tồn tại trong hệ thống
        $user = DB::selectOne('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);

        if (! $user) {
            // Chống mail enumeration: Trả về thông báo thành công chung chung nhưng ko gửi email
            return response()->json([
                'status' => 'success',
                'message' => 'Nếu email tồn tại, chúng tôi đã gửi mã OTP.',
            ]);
        }

        // Tạo mã OTP 6 số ngẫu nhiên
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes(15);

        // Xóa OTP cũ của email này (nếu có)
        DB::delete('DELETE FROM password_resets_otp WHERE email = ?', [$email]);

        $hashedOtp = Hash::make($otp);

        // Lưu OTP mới (đã mã hóa)
        DB::insert(
            'INSERT INTO password_resets_otp (email, otp, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$email, $hashedOtp, $expiresAt->toDateTimeString(), $now->toDateTimeString()]
        );

        // Gửi email chứa mã OTP qua SMTP
        $emailSent = $this->sendOtpEmail($email, $otp, $user->full_name);

        if (! $emailSent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gửi email. Vui lòng thử lại sau!',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Nếu email tồn tại, chúng tôi đã gửi mã OTP. Mã có hiệu lực trong 15 phút.',
        ]);
    }

    /**
     * Bước 2: Xác thực mã OTP
     */
    public function verifyOtp(Request $request)
    {
        $email = $request->input('email');
        $otp = $request->input('otp');

        if (! $email || ! $otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng nhập đầy đủ email và mã OTP!',
            ], 422);
        }

        // Tìm record theo email
        $record = DB::selectOne(
            'SELECT * FROM password_resets_otp WHERE email = ?',
            [$email]
        );

        if (! $record || ! Hash::check($otp, $record->otp)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP không chính xác!',
            ], 422);
        }

        // Kiểm tra hết hạn
        if (Carbon::parse($record->expires_at)->isPast()) {
            // Xóa OTP đã hết hạn
            DB::delete('DELETE FROM password_resets_otp WHERE email = ?', [$email]);

            return response()->json([
                'status' => 'error',
                'message' => 'Mã OTP đã hết hạn! Vui lòng yêu cầu mã mới.',
            ], 422);
        }

        // Tạo reset_token tạm thời (hash email + hashedOtp + secret)
        $resetToken = hash('sha256', $email.$record->otp.config('app.key'));

        return response()->json([
            'status' => 'success',
            'message' => 'Xác thực OTP thành công!',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Bước 3: Đặt lại mật khẩu mới
     */
    public function resetPassword(Request $request)
    {
        $email = $request->input('email');
        $resetToken = $request->input('reset_token');
        $password = $request->input('password');
        $passwordConfirmation = $request->input('password_confirmation');

        // Validate inputs
        if (! $email || ! $resetToken || ! $password || ! $passwordConfirmation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vui lòng nhập đầy đủ thông tin!',
            ], 422);
        }

        if ($password !== $passwordConfirmation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu xác nhận không khớp!',
            ], 422);
        }

        // Password validation: chữ hoa + số + ký tự đặc biệt + tối thiểu 8 ký tự
        if (strlen($password) < 8) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu phải có ít nhất 8 ký tự!',
            ], 422);
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu phải chứa ít nhất 1 chữ hoa!',
            ], 422);
        }

        if (! preg_match('/[0-9]/', $password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu phải chứa ít nhất 1 chữ số!',
            ], 422);
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt!',
            ], 422);
        }

        // Verify reset_token
        $otpRecord = DB::selectOne('SELECT * FROM password_resets_otp WHERE email = ?', [$email]);

        if (! $otpRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng thử lại!',
            ], 422);
        }

        if (Carbon::parse($otpRecord->expires_at)->isPast()) {
            DB::delete('DELETE FROM password_resets_otp WHERE email = ?', [$email]);

            return response()->json([
                'status' => 'error',
                'message' => 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng thử lại!',
            ], 422);
        }

        $expectedToken = hash('sha256', $email.$otpRecord->otp.config('app.key'));

        if (! hash_equals($expectedToken, $resetToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token không hợp lệ. Vui lòng thử lại!',
            ], 422);
        }

        // Cập nhật mật khẩu mới
        $hashedPassword = Hash::make($password);
        DB::update('UPDATE users SET password = ?, updated_at = ? WHERE email = ?', [
            $hashedPassword,
            Carbon::now()->toDateTimeString(),
            $email,
        ]);

        // Xóa tất cả OTP records của email
        DB::delete('DELETE FROM password_resets_otp WHERE email = ?', [$email]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập với mật khẩu mới.',
        ]);
    }

    /**
     * Gửi email OTP qua SMTP (sử dụng PHPMailer-style với mail())
     */
    private function sendOtpEmail(string $email, string $otp, string $name): bool
    {
        try {
            $emailUser = config('mail.mailers.smtp.username', config('services.email.user'));
            $emailPass = config('mail.mailers.smtp.password', config('services.email.pass'));

            // Sử dụng Symfony Mailer qua SMTP (port 587 = STARTTLS)
            $transport = new EsmtpTransport(
                'smtp.gmail.com',
                587,
                false // false = STARTTLS (auto-upgrade), true = SSL trực tiếp (port 465)
            );
            $transport->setUsername($emailUser);
            $transport->setPassword($emailPass);

            $mailer = new Mailer($transport);

            $otpDigits = str_split($otp);
            $otpBoxes = '';
            foreach ($otpDigits as $digit) {
                $otpBoxes .= '<td style="padding: 0 4px;"><div style="width: 48px; height: 56px; background: #FFF0F3; border: 2px solid #E63B6F; border-radius: 10px; font-size: 26px; font-weight: 800; color: #b50c4d; line-height: 56px; text-align: center; font-family: \'Courier New\', monospace;">'.$digit.'</div></td>';
            }

            $htmlBody = '
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
                            <!-- Header -->
                            <tr><td style="background: linear-gradient(135deg, #E63B6F 0%, #b50c4d 100%); padding: 32px 24px; text-align: center;">
                                <div style="font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px; color: #ffd9de;">OCEAN SPORT</div>
                                <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 800; letter-spacing: 0.5px;">Mã OTP Đặt Lại Mật Khẩu</h1>
                                <p style="color: #ffd9de; font-size: 13px; margin: 6px 0 0;">Bảo mật thông tin tài khoản của bạn</p>
                            </td></tr>

                            <!-- Body -->
                            <tr><td style="padding: 32px 28px 24px;">
                                <p style="color: #1e293b; font-size: 15px; margin: 0 0 8px; line-height: 1.5;">Xin chào <strong style="color: #0f172a;">'.htmlspecialchars($name).'</strong>,</p>
                                <p style="color: #64748b; font-size: 14px; margin: 0 0 24px; line-height: 1.6;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng nhập mã xác thực OTP 6 số bên dưới để tiếp tục:</p>

                                <!-- OTP Boxes -->
                                <table cellpadding="0" cellspacing="0" style="margin: 0 auto 24px;">
                                    <tr>'.$otpBoxes.'</tr>
                                </table>

                                <!-- Timer Warning -->
                                <div style="background: #FFF0F3; border: 1px solid #ffd9de; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;">
                                    <p style="color: #b50c4d; font-size: 13px; margin: 0; text-align: center; line-height: 1.5; font-weight: 600;">
                                        ⏰ Mã có hiệu lực trong <strong>15 phút</strong>. Tuyệt đối không chia sẻ mã này với bất kỳ ai!
                                    </p>
                                </div>

                                <!-- Security Note -->
                                <p style="color: #94a3b8; font-size: 12px; margin: 0; line-height: 1.5; text-align: center;">
                                    Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.<br>
                                    Tài khoản của bạn vẫn được an toàn.
                                </p>
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

            $emailMessage = (new Email)
                ->from($emailUser)
                ->to($email)
                ->subject('[Ocean Sport] Mã OTP đặt lại mật khẩu của bạn')
                ->html($htmlBody);

            $mailer->send($emailMessage);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: '.$e->getMessage());

            return false;
        }
    }
}
