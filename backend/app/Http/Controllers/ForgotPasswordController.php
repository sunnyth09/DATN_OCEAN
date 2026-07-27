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
                $otpBoxes .= '<td style="padding: 0 4px;"><div style="width: 48px; height: 56px; background: #f0f4ff; border: 2px solid #4f6ef7; border-radius: 10px; font-size: 26px; font-weight: 700; color: #1a1a2e; line-height: 56px; text-align: center; font-family: \'Courier New\', monospace;">'.$digit.'</div></td>';
            }

            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="margin: 0; padding: 0; background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background: #f0f2f5; padding: 40px 20px;">
                    <tr><td align="center">
                        <table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.08);">
                            <!-- Header -->
                            <tr><td style="background: linear-gradient(135deg, #4f6ef7 0%, #6366f1 100%); padding: 28px 32px; text-align: center;">
                                <h1 style="color: #ffffff; font-size: 20px; margin: 0; font-weight: 600; letter-spacing: 0.5px;">Đặt lại mật khẩu</h1>
                                <p style="color: rgba(255,255,255,0.8); font-size: 13px; margin: 6px 0 0;">Ocean Store</p>
                            </td></tr>

                            <!-- Body -->
                            <tr><td style="padding: 32px 32px 24px;">
                                <p style="color: #1a1a2e; font-size: 15px; margin: 0 0 8px; line-height: 1.5;">Xin chào <strong>'.htmlspecialchars($name).'</strong>,</p>
                                <p style="color: #6b7280; font-size: 14px; margin: 0 0 28px; line-height: 1.6;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng sử dụng mã xác thực bên dưới:</p>

                                <!-- OTP Boxes -->
                                <table cellpadding="0" cellspacing="0" style="margin: 0 auto 28px;">
                                    <tr>'.$otpBoxes.'</tr>
                                </table>

                                <!-- Timer Warning -->
                                <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;">
                                    <p style="color: #92400e; font-size: 13px; margin: 0; text-align: center; line-height: 1.5;">
                                        Mã có hiệu lực trong <strong>15 phút</strong>. Không chia sẻ mã này với bất kỳ ai.
                                    </p>
                                </div>

                                <!-- Security Note -->
                                <p style="color: #9ca3af; font-size: 12px; margin: 0; line-height: 1.5; text-align: center;">
                                    Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.<br>
                                    Tài khoản của bạn vẫn an toàn.
                                </p>
                            </td></tr>

                            <!-- Footer -->
                            <tr><td style="background: #f9fafb; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
                                <p style="color: #9ca3af; font-size: 11px; margin: 0; text-align: center; line-height: 1.5;">
                                    © '.date('Y').' Ocean Fashion. All rights reserved.<br>
                                    Email này được gửi tự động, vui lòng không trả lời.
                                </p>
                            </td></tr>
                        </table>
                    </td></tr>
                </table>
            </body>
            </html>';

            $emailMessage = (new Email)
                ->from($emailUser)
                ->to($email)
                ->subject('Mã OTP đặt lại mật khẩu - Ocean Store')
                ->html($htmlBody);

            $mailer->send($emailMessage);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: '.$e->getMessage());

            return false;
        }
    }
}
