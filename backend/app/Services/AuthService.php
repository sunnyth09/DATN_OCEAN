<?php

namespace App\Services;

use App\Http\Resources\UserProfileResource;
use App\Models\Admin;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    // ─── CAPTCHA ───────────────────────────────────────────────────────

    /**
     * Kiểm tra xem request có phải từ Mobile App thật không.
     * Chỉ nhận diện qua User-Agent chính xác của Flutter OceanShop.
     * Không dùng flag `is_mobile` để tránh bị giả mạo bằng Postman/curl.
     */
    public function isMobileRequest(): bool
    {
        $userAgent = request()->header('User-Agent', '');

        return str_contains($userAgent, 'Flutter OceanShop');
    }

    /**
     * Xác thực Cloudflare Turnstile token.
     * Bỏ qua hoàn toàn nếu request đến từ Mobile App thật (User-Agent Flutter OceanShop).
     */
    public function verifyTurnstile(?string $token, array $data = []): bool
    {
        // Bỏ qua CAPTCHA chỉ khi User-Agent khớp với Mobile App thật
        if ($this->isMobileRequest()) {
            \Log::info('verifyTurnstile: skipped for mobile request (UA match)');

            return true;
        }

        \Log::info('verifyTurnstile called', ['env' => app()->environment(), 'token' => $token]);
        // Tắt CAPTCHA khi đang ở môi trường local/dev (không phải testing qua phpunit)
        if (app()->environment('local') && PHP_SAPI !== 'cli') {
            return true;
        }

        if (! $token) {
            return false;
        }

        $secretKey = config('services.turnstile.secret_key');
        if (! $secretKey) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
            ]);

            return $response->json('success', false);
        } catch (\Exception $e) {
            Log::error('Turnstile verification failed: '.$e->getMessage());

            return false;
        }
    }

    // ─── PASSWORD VALIDATION ───────────────────────────────────────────

    /**
     * Validate password: chữ hoa + số + ký tự đặc biệt + tối thiểu 8 ký tự
     */
    public function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự!';
        }
        if (! preg_match('/[A-Z]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ hoa!';
        }
        if (! preg_match('/[0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 chữ số!';
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt!';
        }

        return null;
    }

    // ─── REGISTER ──────────────────────────────────────────────────────

    public function register(array $data): array
    {
        $validator = Validator::make($data, [
            'full_name' => ['nullable', 'required_without:name', 'string', 'max:120'],
            'name' => ['nullable', 'required_without:full_name', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return ['_status' => 422, 'status' => 'error', 'errors' => $validator->errors()->toArray()];
        }

        // Turnstile
        \Log::info('Register called', ['turnstile' => $data['turnstile_token'] ?? 'NOT_SET', 'is_mobile' => $data['is_mobile'] ?? 'NOT_SET']);
        if (! $this->verifyTurnstile($data['turnstile_token'] ?? null, $data)) {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'];
        }

        $name = trim($data['full_name'] ?? $data['name']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];

        // Password validation
        $passwordError = $this->validatePassword($password);
        if ($passwordError) {
            return ['_status' => 422, 'status' => 'error', 'message' => $passwordError];
        }

        // Check email
        if ($this->userRepository->emailExists($email)) {
            return ['_status' => 422, 'status' => 'error', 'errors' => ['email' => ['Địa chỉ email này đã được sử dụng!']]];
        }

        // Create user
        $this->userRepository->createUser([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'customer',
        ]);

        // Login
        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);
        $user = auth('api')->user();

        if (! $token || ! $user) {
            return ['_status' => 500, 'status' => 'error', 'message' => 'Không thể khởi tạo phiên đăng nhập. Vui lòng đăng nhập lại.'];
        }

        return [
            '_status' => 201,
            'status' => 'success',
            'message' => 'Đăng ký tài khoản thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'role' => $user->role,
            'user' => $user,
        ];
    }

    // ─── LOGIN ─────────────────────────────────────────────────────────

    public function login(array $data): array
    {
        $validator = Validator::make($data, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return ['_status' => 422, 'status' => 'error', 'errors' => $validator->errors()->toArray()];
        }

        // Turnstile
        if (! $this->verifyTurnstile($data['turnstile_token'] ?? null, $data)) {
            return ['_status' => 422, 'status' => 'error', 'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'];
        }

        $credentials = ['email' => strtolower(trim($data['email'])), 'password' => $data['password']];

        // Thử admin trước
        if ($token = auth('admin')->attempt($credentials)) {
            $user = auth('admin')->user();
            if (isset($user->status) && $user->status !== 'active') {
                auth('admin')->logout();

                return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'];
            }

            return $this->respondWithToken($token, 'admin');
        }

        // Thử customer
        if ($token = auth('api')->attempt($credentials)) {
            $user = auth('api')->user();
            if (isset($user->status) && $user->status !== 'active') {
                auth('api')->logout();

                return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'];
            }
            if ($user->deleted_at !== null) {
                auth('api')->logout();

                return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị xóa khỏi hệ thống!'];
            }

            return $this->respondWithToken($token, 'customer');
        }

        return ['_status' => 401, 'status' => 'error', 'message' => 'Email hoặc mật khẩu không chính xác!'];
    }

    // ─── REFRESH / ME / LOGOUT ─────────────────────────────────────────

    public function refresh(): array
    {
        try {
            $newToken = auth('admin')->refresh();

            return [
                '_status' => 200,
                'status' => 'success',
                'access_token' => $newToken,
                'refresh_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => auth('admin')->factory()->getTTL() * 60,
            ];
        } catch (\Exception $e) {
            // Not admin guard
        }

        try {
            $newToken = auth('api')->refresh();

            return [
                '_status' => 200,
                'status' => 'success',
                'access_token' => $newToken,
                'refresh_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ];
        } catch (\Exception $e) {
            return ['_status' => 401, 'status' => 'error', 'message' => 'Token không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.'];
        }
    }

    // FIX C1: Dùng UserProfileResource để lọc data nhạy cảm
    public function me(): array
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        $user = auth($guard)->user();

        return ['status' => 'success', 'user' => new UserProfileResource($user)];
    }

    public function logout(): array
    {
        $guard = auth('admin')->check() ? 'admin' : 'api';
        auth($guard)->logout();

        return ['status' => 'success', 'message' => 'Đã đăng xuất thành công!'];
    }

    // ─── GOOGLE OAUTH ──────────────────────────────────────────────────

    public function googleCallback(string $code, ?string $redirectUri = null): array
    {
        try {
            $redirect = ! empty($redirectUri) ? $redirectUri : config('services.google.redirect');
            // Exchange code for token
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => trim((string) config('services.google.client_id')),
                'client_secret' => trim((string) config('services.google.client_secret')),
                'redirect_uri' => trim((string) $redirect),
                'grant_type' => 'authorization_code',
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Google token exchange failed: '.$tokenResponse->body());

                return ['_status' => 401, 'status' => 'error', 'message' => 'Xác thực Google thất bại! Vui lòng thử lại.'];
            }

            // Get user info
            $userResponse = Http::withToken($tokenResponse->json('access_token'))
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if ($userResponse->failed()) {
                return ['_status' => 401, 'status' => 'error', 'message' => 'Không thể lấy thông tin từ Google!'];
            }

            $googleUser = $userResponse->json();
            $googleId = $googleUser['id'];
            $googleEmail = $googleUser['email'];
            $googleName = $googleUser['name'] ?? $googleUser['email'];
            $googleAvatar = $googleUser['picture'] ?? null;

            // Kiểm tra xem email này có thuộc về một Admin không
            $admin = Admin::where('email', $googleEmail)->first();
            if ($admin) {
                if (isset($admin->status) && $admin->status !== 'active') {
                    return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'];
                }

                $token = auth('admin')->login($admin);

                return [
                    '_status' => 200,
                    'status' => 'success',
                    'message' => 'Đăng nhập Google thành công!',
                    'access_token' => $token,
                    'refresh_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => auth('admin')->factory()->getTTL() * 60,
                    'role' => $admin->role ?? 'admin',
                    'user' => clone $admin,
                ];
            }

            // Find or create user (Customer)
            $user = $this->findOrCreateOAuthUser('google', $googleId, $googleEmail, $googleName, $googleAvatar);

            if (is_array($user) && isset($user['_status'])) {
                return $user;
            } // error response

            // JWT
            $model = $this->userRepository->findModel($user->user_id);
            $token = auth('api')->login($model);

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công!',
                'access_token' => $token,
                'refresh_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'role' => $user->role ?? 'customer',
                'user' => clone $user,
            ];
        } catch (\Exception $e) {
            Log::error('Google login error: '.$e->getMessage());

            return ['_status' => 500, 'status' => 'error', 'message' => 'Đăng nhập Google thất bại! Vui lòng thử lại.'];
        }
    }

    public function googleMobileLogin(?string $idToken, ?string $googleId, ?string $email, ?string $name, ?string $avatar): array
    {
        try {
            if ($idToken) {
                try {
                    $response = Http::timeout(5)->get('https://oauth2.googleapis.com/tokeninfo', [
                        'id_token' => $idToken,
                    ]);

                    if ($response->successful()) {
                        $payload = $response->json();
                        $googleId = $payload['sub'] ?? $googleId;
                        $email = $payload['email'] ?? $email;
                        $name = $payload['name'] ?? $name ?? 'Google User';
                        $avatar = $payload['picture'] ?? $avatar;
                    }
                } catch (\Exception $ex) {
                    Log::warning('Google tokeninfo check skipped/failed: '.$ex->getMessage());
                }
            }

            if (! $email) {
                return ['_status' => 422, 'status' => 'error', 'message' => 'Không tìm thấy thông tin email từ Google!'];
            }

            // Check if admin
            $admin = Admin::where('email', $email)->first();
            if ($admin) {
                if (isset($admin->status) && $admin->status !== 'active') {
                    return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'];
                }

                $token = auth('admin')->login($admin);

                return [
                    '_status' => 200,
                    'status' => 'success',
                    'message' => 'Đăng nhập Google thành công!',
                    'access_token' => $token,
                    'refresh_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => auth('admin')->factory()->getTTL() * 60,
                    'role' => $admin->role ?? 'admin',
                    'user' => clone $admin,
                ];
            }

            // Find or create customer
            $user = $this->findOrCreateOAuthUser('google', $googleId ?? $email, $email, $name ?? 'Google User', $avatar);

            if (is_array($user) && isset($user['_status'])) {
                return $user;
            }

            $model = $this->userRepository->findModel($user->user_id);
            $token = auth('api')->login($model);

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Đăng nhập Google thành công!',
                'access_token' => $token,
                'refresh_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'role' => $user->role ?? 'customer',
                'user' => clone $user,
            ];
        } catch (\Exception $e) {
            Log::error('Google Mobile login error: '.$e->getMessage());

            return ['_status' => 500, 'status' => 'error', 'message' => 'Đăng nhập Google thất bại: '.$e->getMessage()];
        }
    }

    // ─── FACEBOOK OAUTH ────────────────────────────────────────────────

    public function facebookCallback(string $code, ?string $redirectUri = null): array
    {
        try {
            $redirect = ! empty($redirectUri) ? $redirectUri : config('services.facebook.redirect');
            // Exchange code for token
            $tokenResponse = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                'client_id' => trim((string) config('services.facebook.client_id')),
                'client_secret' => trim((string) config('services.facebook.client_secret')),
                'redirect_uri' => trim((string) $redirect),
                'code' => $code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Facebook token exchange failed: '.$tokenResponse->body());

                return ['_status' => 401, 'status' => 'error', 'message' => 'Xác thực Facebook thất bại! Vui lòng thử lại.'];
            }

            // Get user info
            $userResponse = Http::get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture.type(large)',
                'access_token' => $tokenResponse->json('access_token'),
            ]);

            if ($userResponse->failed()) {
                return ['_status' => 401, 'status' => 'error', 'message' => 'Không thể lấy thông tin từ Facebook!'];
            }

            $fbUser = $userResponse->json();
            $fbId = $fbUser['id'];
            $fbEmail = $fbUser['email'] ?? ($fbId.'@facebook.local');
            $fbName = $fbUser['name'] ?? 'Facebook User';
            $fbAvatar = $fbUser['picture']['data']['url'] ?? null;

            // Kiểm tra xem email này có thuộc về một Admin không
            $admin = Admin::where('email', $fbEmail)->first();
            if ($admin) {
                if (isset($admin->status) && $admin->status !== 'active') {
                    return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'];
                }

                $token = auth('admin')->login($admin);

                return [
                    '_status' => 200,
                    'status' => 'success',
                    'message' => 'Đăng nhập Facebook thành công!',
                    'access_token' => $token,
                    'refresh_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => auth('admin')->factory()->getTTL() * 60,
                    'role' => $admin->role ?? 'admin',
                    'user' => clone $admin,
                ];
            }

            // Find or create user (Customer)
            $user = $this->findOrCreateOAuthUser('facebook', $fbId, $fbEmail, $fbName, $fbAvatar);

            if (is_array($user) && isset($user['_status'])) {
                return $user;
            }

            // JWT
            // JWT
            $model = $this->userRepository->findModel($user->user_id);
            $token = auth('api')->login($model);

            return [
                '_status' => 200,
                'status' => 'success',
                'message' => 'Đăng nhập Facebook thành công!',
                'access_token' => $token,
                'refresh_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'role' => $user->role ?? 'customer',
                'user' => clone $user,
            ];
        } catch (\Exception $e) {
            Log::error('Facebook login error: '.$e->getMessage());

            return ['_status' => 500, 'status' => 'error', 'message' => 'Đăng nhập Facebook thất bại! Vui lòng thử lại.'];
        }
    }

    // ─── PRIVATE HELPERS ───────────────────────────────────────────────

    private function respondWithToken(string $token, string $guardType): array
    {
        $user = ($guardType === 'admin') ? auth('admin')->user() : auth('api')->user();

        return [
            '_status' => 200,
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'role' => $user->role,
            'user' => $user,
        ];
    }

    /**
     * Tìm hoặc tạo user từ OAuth provider (Google/Facebook)
     * Chung logic cho cả 2 provider
     */
    private function findOrCreateOAuthUser(string $provider, string $providerId, string $email, string $name, ?string $avatar)
    {
        // Tìm bằng provider ID trước
        $user = $provider === 'google'
            ? $this->userRepository->findByGoogleId($providerId)
            : $this->userRepository->findByFacebookId($providerId);

        // Check deleted/inactive
        if ($user) {
            $check = $this->checkUserStatus($user);
            if ($check) {
                return $check;
            }

            return $user;
        }

        // Tìm bằng email (account linking)
        $user = $this->userRepository->findByEmail($email);

        if ($user) {
            $check = $this->checkUserStatus($user);
            if ($check) {
                return $check;
            }

            // Link provider
            if ($provider === 'google') {
                $this->userRepository->linkGoogleId($user->user_id, $providerId, $avatar);
            } else {
                $this->userRepository->linkFacebookId($user->user_id, $providerId, $avatar);
            }

            return $user;
        }

        // Create new user
        if ($provider === 'google') {
            $this->userRepository->createGoogleUser($name, $email, $providerId, $avatar);

            return $this->userRepository->findByGoogleId($providerId);
        } else {
            $this->userRepository->createFacebookUser($name, $email, $providerId, $avatar);

            return $this->userRepository->findByFacebookId($providerId);
        }
    }

    private function checkUserStatus($user): ?array
    {
        if ($user->deleted_at !== null) {
            return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị xóa khỏi hệ thống!'];
        }
        if (isset($user->status) && $user->status !== 'active') {
            return ['_status' => 403, 'status' => 'error', 'message' => 'Tài khoản của bạn đã bị vô hiệu hóa hoặc khóa!'];
        }

        return null;
    }
}
