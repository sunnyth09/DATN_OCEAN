<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = $admin->notifications();

        if ($request->query('unread_only') === 'true') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'notifications' => $notifications->items(),
            'total' => $notifications->total(),
            'unread_count' => $admin->unreadNotifications()->count(),
            'last_page' => $notifications->lastPage(),
            'current_page' => $notifications->currentPage(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) return response()->json(['success' => false], 401);
        
        $notification = $admin->notifications()->where('id', $id)->first();
        
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $admin->unreadNotifications()->count()
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin) {
            $admin->unreadNotifications->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin) return response()->json(['success' => false], 401);
        
        $notification = $admin->notifications()->where('id', $id)->first();
        
        if ($notification) {
            $notification->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }
}
