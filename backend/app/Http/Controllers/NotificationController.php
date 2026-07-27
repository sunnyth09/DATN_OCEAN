<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * =====================================================================
 * NotificationController — API quản lý Notification inbox
 * =====================================================================
 *
 * Cung cấp các endpoint cho frontend đọc/đánh dấu notification:
 * - GET  /api/notifications         → Lấy danh sách thông báo
 * - POST /api/notifications/{id}/read → Đánh dấu đã đọc
 * - POST /api/notifications/read-all  → Đánh dấu tất cả đã đọc
 * - GET  /api/reward-points           → Xem điểm thưởng
 *
 * TẤT CẢ đều cần JWT authentication (guard: api hoặc admin)
 */
class NotificationController extends Controller
{
    /**
     * index() — Lấy danh sách notification của user đang đăng nhập
     *
     * Logic:
     * 1. Lấy user từ JWT token (auth('api') hoặc auth('admin'))
     * 2. Dùng notifications() relationship (từ Notifiable trait)
     * 3. Sắp xếp mới nhất lên đầu (latest())
     * 4. Phân trang 20 bản ghi/trang
     *
     * Cú pháp:
     * - auth('api')->user() → lấy user đã authenticate
     * - ->notifications()   → relationship từ Notifiable trait → bảng notifications
     * - ->latest()          → orderBy('created_at', 'desc')
     * - ->paginate(20)      → phân trang 20 records/page
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = $user->notifications()->where(function ($q) {
            $q->whereNull('data->url_redirect')
              ->orWhere('data->url_redirect', 'not like', '/admin/%');
        });

        $limit = request('limit', 10);
        
        // Lấy notifications phân trang, mới nhất lên đầu
        $notifications = $query->clone()
            ->latest()
            ->paginate($limit);

        // Đếm số notification chưa đọc
        $unreadCount = $query->clone()->whereNull('read_at')->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'data'         => $notifications,
        ]);
    }

    /**
     * markAsRead() — Đánh dấu 1 notification đã đọc
     *
     * Logic:
     * 1. Tìm notification theo UUID id
     * 2. Gọi markAsRead() → set read_at = now()
     *
     * Cú pháp:
     * - ->notifications()->findOrFail($id) → tìm notification theo UUID
     * - ->markAsRead() → set cột read_at = Carbon::now()
     *
     * @param string $id UUID của notification
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead(); // Set read_at = now()

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã đánh dấu đã đọc',
        ]);
    }

    /**
     * markAllAsRead() — Đánh dấu TẤT CẢ notification đã đọc
     *
     * Logic:
     * - unreadNotifications() → lấy tất cả notification có read_at = null
     * - ->update(['read_at' => now()]) → cập nhật hàng loạt
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã đánh dấu tất cả đã đọc',
        ]);
    }

    /**
     * rewardPoints() — Xem điểm thưởng hiện tại của user
     *
     * Trả về: reward_points và full_name
     */
    public function rewardPoints(): JsonResponse
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'full_name'     => $user->full_name,
                'reward_points' => $user->reward_points ?? 0,
            ],
        ]);
    }
}
