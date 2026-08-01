<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = auth('api')->user() ?? auth('admin')->user();

        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $query = $admin->notifications();

        if ($request->query('unread_only') === 'true') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate((int) $request->query('per_page', 10));

        $items = $notifications->items();
        $orderIds = [];

        // Extract order IDs
        foreach ($items as $noti) {
            if (isset($noti->data['order_id'])) {
                $orderIds[] = $noti->data['order_id'];
            } elseif (isset($noti->data['url_redirect']) && preg_match('/\/admin\/order\/(\d+)/', $noti->data['url_redirect'], $matches)) {
                $orderIds[] = $matches[1];
                $data = $noti->data;
                $data['order_id'] = $matches[1];
                $noti->data = $data;
            }
        }

        // Fetch current status for orders and update data array
        if (! empty($orderIds)) {
            $orders = Order::whereIn('order_id', array_unique($orderIds))->get()->keyBy('order_id');
            foreach ($items as $noti) {
                if (isset($noti->data['order_id']) && isset($orders[$noti->data['order_id']])) {
                    $order = $orders[$noti->data['order_id']];
                    $data = $noti->data;
                    $data['payment_status'] = $order->payment_status;
                    $data['fulfillment_status'] = $order->fulfillment_status;
                    $noti->data = $data;
                }
            }
        }

        return response()->json([
            'success' => true,
            'notifications' => $items,
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
        $admin = auth('api')->user() ?? auth('admin')->user();

        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notification = $admin->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $admin->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $admin = auth('api')->user() ?? auth('admin')->user();

        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $admin->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $admin = auth('api')->user() ?? auth('admin')->user();

        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $notification = $admin->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }
}
