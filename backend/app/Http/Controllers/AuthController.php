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
        $redirectUri = $request->input('redirect_uri');

        if (! $code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu mã xác thực từ Google!',
            ], 422);
        }

        $result = $this->authService->googleCallback($code, $redirectUri);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }

    /**
     * Google Mobile Sign-In (ID Token / Direct Payload)
     */
    public function googleMobileLogin(Request $request)
    {
        $idToken = $request->input('id_token');
        $googleId = $request->input('google_id');
        $email = $request->input('email');
        $name = $request->input('name');
        $avatar = $request->input('avatar_url');

        $result = $this->authService->googleMobileLogin($idToken, $googleId, $email, $name, $avatar);
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
        $redirectUri = $request->input('redirect_uri');

        if (! $code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu mã xác thực từ Facebook!',
            ], 422);
        }

        $result = $this->authService->facebookCallback($code, $redirectUri);
        $status = $result['_status'] ?? 200;
        unset($result['_status']);

        return response()->json($result, $status);
    }
}
