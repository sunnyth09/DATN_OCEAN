<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        // Validate dữ liệu từ Flutter gửi lên
        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string' // android hoặc ios
        ]);

        // Lấy user đang đăng nhập (nhờ middleware auth:sanctum)
        $user = $request->user();

        // Lưu hoặc cập nhật token vào bảng user_devices mà bạn đã tạo migration
        $user->devices()->updateOrCreate(
            ['fcm_token' => $request->fcm_token],
            ['device_type' => $request->device_type]
        );

        return response()->json([
            'success' => true,
            'message' => 'Lưu Device Token thành công!'
        ]);
    }
}
