<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtpMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Gửi email OTP qua Queue (không block request của người dùng)
     */
    private function sendOtpEmail(string $email, string $otp, string $name): bool
    {
        try {
            Mail::to($email)->queue(new PasswordResetOtpMail($otp, $email));

            Log::info("Password reset OTP email queued for: {$email}");

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to queue OTP email: '.$e->getMessage());

            return false;
        }
    }
}
