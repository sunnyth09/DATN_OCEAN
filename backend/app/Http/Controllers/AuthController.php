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
        $result = $this->authService->register($request->all());
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
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

        // BƯỚC 1: Thử đăng nhập Admin (nhân sự) trước
        if ($token = auth('admin')->attempt($credentials)) {
            $user = auth('admin')->user();
            if (isset($user->status) && $user->status !== 'active') {
                auth('admin')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tài khoản của bạn đã bị khóa hoặc vô hiệu hóa!'
                ], 403);
            }
            return $this->respondWithToken($token, 'admin');
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

        return response()->json($result, $status);
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