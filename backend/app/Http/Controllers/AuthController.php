<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Xác thực Cloudflare Turnstile token
     */
    private function verifyTurnstile(?string $token): bool
    {
        // Bypass CAPTCHA for Mobile App (Flutter) — check Dart User-Agent OR is_mobile flag
        $userAgent = request()->userAgent() ?? '';
        $isMobileApp = request()->input('is_mobile') == true;
        $hasDartAgent = str_contains(strtolower($userAgent), 'dart');

        if ($hasDartAgent || ($isMobileApp && empty($token))) {
            return true;
        }

        if (!$token) {
            return false;
        }

        $secretKey = config('services.turnstile.secret_key');

        if (!$secretKey) {
            // Tắt bypass dev mode, luôn bắt buộc cấu hình Turnstile
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return $response->json('success', false);
        } catch (\Exception $e) {
            Log::error('Turnstile verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate password: chữ hoa + số + ký tự đặc biệt + tối thiểu 8 ký tự
     */
    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự!';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ hoa!';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ số!';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt!';
        }
        return null; // Valid
    }

    public function register(Request $request)
    {
        // Verify Cloudflare Turnstile
        $turnstileToken = $request->input('turnstile_token');
        if (!$this->verifyTurnstile($turnstileToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'
            ], 422);
        }

        $name = $request->input('full_name') ?? $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');

        // Password validation
        $passwordError = $this->validatePassword($password);
        if ($passwordError) {
            return response()->json([
                'status' => 'error',
                'message' => $passwordError
            ], 422);
        }

        $checkEmail = DB::select("SELECT * FROM users WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422);
        }

        $hashedPassword = Hash::make($password);
        $now = Carbon::now()->toDateTimeString();

        DB::insert(
            "INSERT INTO users (full_name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $email, $hashedPassword, 'customer', $now, $now]
        );

        $credentials = ['email' => $email, 'password' => $password];
        $token = auth('api')->attempt($credentials);

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 201);
    }

    public function login(Request $request)
    {
        // VÔ HIỆU HOÁ CAPTRA TRÁNH BỊ LỖI KHI ĐĂNG NHẬP
        // Lý do:
        // - KEY CAPTCHA CỦA CLAUDFLRE CHƯA ĐƯỢC CẤU HÌNH ĐÚNG
        
        // Verify Cloudflare Turnstile
        // $turnstileToken = $request->input('turnstile_token');
        // if (!$this->verifyTurnstile($turnstileToken)) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'
        //     ], 422);
        // }

        $credentials = $request->only('email', 'password');
        \Illuminate\Support\Facades\Log::info("Login attempt", $credentials);

        // BƯỚC 1: Thử đăng nhập Admin (nhân sự) trước
        $adminToken = auth('admin')->attempt($credentials);
        \Illuminate\Support\Facades\Log::info("Admin attempt result", ['token' => (bool)$adminToken]);
        if ($adminToken) {
            $user = auth('admin')->user();
            if (isset($user->status) && $user->status !== 'active') {
                auth('admin')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'
                ], 403);
            }
            return $this->respondWithToken($adminToken, 'admin');
        }

        // BƯỚC 2: Thử đăng nhập Customer
        if ($token = auth('api')->attempt($credentials)) {
            $user = auth('api')->user();
            if (isset($user->status) && $user->status !== 'active') {
                auth('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'
                ], 403);
            }
            if ($user->deleted_at !== null) {
                auth('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị xóa khỏi hệ thống!'
                ], 403);
            }
            return $this->respondWithToken($token, 'customer');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Email hoặc mật khẩu không chính xác!'
        ], 401);
    }

    protected function respondWithToken($token, $guardType)
    {
        $user = ($guardType === 'admin') ? auth('admin')->user() : auth('api')->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'role' => $user->role,
            'user' => $user
        ], 200);
    }

    public function refresh()
    {
        // Khi token hết hạn, auth()->check() trả về false cho cả hai guard
        // → không thể dùng check() để detect guard.
        // Giải pháp: thử refresh từng guard, guard nào thành công thì dùng.

        // Thử guard 'admin' trước
        try {
            $newToken = auth('admin')->refresh();
            return response()->json([
                'status'        => 'success',
                'access_token'  => $newToken,
                'refresh_token' => $newToken,
                'token_type'    => 'Bearer',
                'expires_in'    => auth('admin')->factory()->getTTL() * 60,
            ]);
        } catch (\Exception $e) {
            // Không phải admin guard, thử tiếp
        }

        // Thử guard 'api' (customer)
        try {
            $newToken = auth('api')->refresh();
            return response()->json([
                'status'        => 'success',
                'access_token'  => $newToken,
                'refresh_token' => $newToken,
                'token_type'    => 'Bearer',
                'expires_in'    => auth('api')->factory()->getTTL() * 60,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.',
            ], 401);
        }
    }

    public function me()
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        $user = auth($guard)->user();

        return response()->json([
            'status' => 'success',
            'user' => $user
        ]);
    }

    public function logout()
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        auth($guard)->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng xuất thành công!'
        ]);
    }

    /**
     * Google OAuth 2.0 Callback
     * Nhận authorization code từ frontend, đổi lấy user info, tạo/liên kết tài khoản
     */
    public function googleCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu mã xác thực từ Google!'
            ], 422);
        }

        try {
            // Bước 1: Đổi authorization code lấy access_token
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Google token exchange failed: ' . $tokenResponse->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Xác thực Google thất bại! Vui lòng thử lại.'
                ], 401);
            }

            $accessToken = $tokenResponse->json('access_token');

            // Bước 2: Lấy thông tin user từ Google
            $userResponse = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($userResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể lấy thông tin từ Google!'
                ], 401);
            }

            $googleUser = $userResponse->json();
            $googleId = $googleUser['id'];
            $googleEmail = $googleUser['email'];
            $googleName = $googleUser['name'] ?? $googleUser['email'];
            $googleAvatar = $googleUser['picture'] ?? null;

            // Bước 3: Tìm hoặc tạo user (Account Linking)
            $now = Carbon::now()->toDateTimeString();

            // Bỏ điều kiện deleted_at IS NULL ở SQL để lấy được cả user đã xoá (soft delete)
            // Tìm bằng google_id trước
            $user = DB::selectOne("SELECT * FROM users WHERE google_id = ?", [$googleId]);

            if ($user && $user->deleted_at !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị xóa khỏi hệ thống!'
                ], 403);
            }
            if ($user && isset($user->status) && $user->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị vô hiệu hóa hoặc khóa!'
                ], 403);
            }

            if (!$user) {
                // Tìm bằng email (account linking)
                $user = DB::selectOne("SELECT * FROM users WHERE email = ?", [$googleEmail]);

                if ($user) {
                    if ($user->deleted_at !== null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Tài khoản liên kết với email này đã bị xóa!'
                        ], 403);
                    }
                    if (isset($user->status) && $user->status !== 'active') {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Tài khoản liên kết với email này đã bị vô hiệu hóa!'
                        ], 403);
                    }

                    // Liên kết google_id vào tài khoản hiện tại
                    DB::update("UPDATE users SET google_id = ?, avatar_url = COALESCE(avatar_url, ?), updated_at = ? WHERE user_id = ?", [
                        $googleId, $googleAvatar, $now, $user->user_id
                    ]);
                } else {
                    // Tạo user mới (password = null vì login bằng Google)
                    DB::insert(
                        "INSERT INTO users (full_name, email, google_id, password, avatar_url, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$googleName, $googleEmail, $googleId, null, $googleAvatar, 'customer', $now, $now]
                    );
                    $user = DB::selectOne("SELECT * FROM users WHERE google_id = ?", [$googleId]);
                }
            }

            // Bước 4: Generate JWT token
            $token = auth('api')->login(User::find($user->user_id));

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công!',
                'access_token' => $token,
                'refresh_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl', 60) * 60,
                'role' => $user->role,
                'user' => clone $user
            ]);

        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đăng nhập Google thất bại! ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Facebook OAuth 2.0 Callback
     */
    public function facebookCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu mã xác thực từ Facebook!'
            ], 422);
        }

        try {
            // Bước 1: Đổi code lấy access_token
            $tokenResponse = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'redirect_uri' => config('services.facebook.redirect'),
                'code' => $request->code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Facebook token exchange failed: ' . $tokenResponse->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Xác thực Facebook thất bại! Vui lòng thử lại.'
                ], 401);
            }

            $accessToken = $tokenResponse->json('access_token');

            // Bước 2: Lấy thông tin user từ Facebook
            $userResponse = Http::get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture.type(large)',
                'access_token' => $accessToken,
            ]);

            if ($userResponse->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể lấy thông tin từ Facebook!'
                ], 401);
            }

            $facebookUser = $userResponse->json();
            $facebookId = $facebookUser['id'];
            $facebookEmail = $facebookUser['email'] ?? ($facebookId . '@facebook.local');
            $facebookName = $facebookUser['name'] ?? 'Facebook User';
            $facebookAvatar = $facebookUser['picture']['data']['url'] ?? null;

            // Bước 3: Tìm hoặc tạo user (Account Linking)
            $now = Carbon::now()->toDateTimeString();

            // Tìm bằng facebook_id trước
            $user = DB::selectOne("SELECT * FROM users WHERE facebook_id = ?", [$facebookId]);

            if ($user && $user->deleted_at !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị xóa khỏi hệ thống!'
                ], 403);
            }
            if ($user && isset($user->status) && $user->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị vô hiệu hóa hoặc khóa!'
                ], 403);
            }

            if (!$user) {
                // Tìm bằng email (account linking)
                $user = DB::selectOne("SELECT * FROM users WHERE email = ?", [$facebookEmail]);

                if ($user) {
                    if ($user->deleted_at !== null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Tài khoản liên kết với email này đã bị xóa!'
                        ], 403);
                    }
                    if (isset($user->status) && $user->status !== 'active') {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Tài khoản liên kết với email này đã bị vô hiệu hóa!'
                        ], 403);
                    }

                    // Liên kết facebook_id vào tài khoản hiện tại
                    DB::update("UPDATE users SET facebook_id = ?, avatar_url = COALESCE(avatar_url, ?), updated_at = ? WHERE user_id = ?", [
                        $facebookId, $facebookAvatar, $now, $user->user_id
                    ]);
                } else {
                    // Tạo user mới (password = null vì login bằng Facebook)
                    DB::insert(
                        "INSERT INTO users (full_name, email, facebook_id, password, avatar_url, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$facebookName, $facebookEmail, $facebookId, null, $facebookAvatar, 'customer', $now, $now]
                    );
                    $user = DB::selectOne("SELECT * FROM users WHERE facebook_id = ?", [$facebookId]);
                }
            }

            // Bước 4: Generate JWT token
            $token = auth('api')->login(User::find($user->user_id));

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng nhập Facebook thành công!',
                'access_token' => $token,
                'refresh_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl', 60) * 60,
                'role' => $user->role,
                'user' => clone $user
            ]);

        } catch (\Exception $e) {
            Log::error('Facebook login error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đăng nhập Facebook thất bại! ' . $e->getMessage()
            ], 500);
        }
    }
}