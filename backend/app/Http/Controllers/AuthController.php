<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(Request $request)
    {
        $turnstileToken = $request->input('turnstile_token');
        if (!$this->authService->verifyTurnstile($turnstileToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.'
            ], 422);
        }

        $result = $this->authService->register($request->all());
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        // \Illuminate\Support\Facades\Log::info("Login attempt", $credentials);

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
        $guard = ($guardType === 'admin') ? 'admin' : 'api';
        $user  = auth($guard)->user();

        return response()->json([
            'status'        => 'success',
            'message'       => 'Đăng nhập thành công!',
            'access_token'  => $token,
            'refresh_token' => $token,
            'token_type'    => 'Bearer',
            'expires_in'    => auth($guard)->factory()->getTTL() * 60,
            'role'          => $user->role ?? $guardType,
            'user'          => $user,
        ], 200);
    }

    public function refresh()
    {
        $result = $this->authService->refresh();
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    public function me()
    {
        return response()->json($this->authService->me());
    }

    public function logout()
    {
        return response()->json($this->authService->logout());
    }

    /**
     * Google OAuth 2.0 Callback
     */
    public function googleCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Thiếu mã xác thực từ Google!'
            ], 422);
        }

        $result = $this->authService->googleCallback($code);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Facebook OAuth 2.0 Callback
     */
    public function facebookCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Thiếu mã xác thực từ Facebook!'
            ], 422);
        }

        $result = $this->authService->facebookCallback($code);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }
}
