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
        $result = $this->authService->login($request->all());
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

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