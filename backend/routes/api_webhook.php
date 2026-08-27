<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['api', 'auth:api,admin']]);

use App\Http\Controllers\Api\Admin\CourtAdminController;
use App\Http\Controllers\Api\Admin\CourtBookingAdminController;
use App\Http\Controllers\Api\Admin\CourtMaintenanceAdminController;
use App\Http\Controllers\Api\Admin\CourtPriceAdminController;
use App\Http\Controllers\Api\Admin\CourtScheduleAdminController;
use App\Http\Controllers\Api\Admin\CourtServiceAdminController;
use App\Http\Controllers\Api\CourtBookingController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\GhnWebhookController;
use App\Http\Controllers\OceanExpressWebhookController;
use App\Http\Controllers\SepayController;
use App\Http\Controllers\VNPayController;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// use Illuminate\Http\Request;

// VNPay Payment Gateway (Public — VNPay redirect về đây)
// IPN là server-to-server từ VNPay — không throttle, return URL tăng lên 60 cho user retry
Route::middleware('throttle:60,1')->get('/payment/vnpay-return', [VNPayController::class, 'vnpayReturn']);
Route::post('/payment/vnpay-ipn', [VNPayController::class, 'vnpayIpn']);

// SePay Webhook — server-to-server, không throttle
Route::post('/payment/sepay-webhook', [SepayController::class, 'handleWebhook']);
// ██ DEBUG ROUTES — Chạy thủ công scheduler commands (XÓA KHI PRODUCTION)
// ⚠️ DEBUG ROUTES — Được bảo vệ bởi auth + role:admin (FIX C7: không còn public)
Route::middleware(['auth:api,admin', 'role:admin'])->group(function () {
    Route::get('/run-abandoned-cart', function () {
        try {
            Artisan::call('app:remind-abandoned-cart');

            return response()->json(['status' => 'success', 'output' => Artisan::output()]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });

    Route::get('/run-birthday', function () {
        try {
            Artisan::call('app:send-birthday-wishes');

            return response()->json(['status' => 'success', 'output' => Artisan::output()]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });

    Route::get('/run-send-all-test-emails', function (Request $request) {
        try {
            $email = $request->query('email', 'levanvu06102004kimanh@gmail.com');
            Artisan::call('mail:test-batch', ['email' => $email]);

            return response()->json([
                'status' => 'success',
                'target_email' => $email,
                'output' => Artisan::output(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });

    Route::get('/cart-status', function () {
        $carts = Cart::where('status', 'active')
            ->whereHas('items')
            ->with(['user:user_id,full_name,email,reward_points', 'items'])
            ->get()
            ->map(function ($cart) {
                $latestItem = $cart->items->sortByDesc('updated_at')->first();

                return [
                    'cart_id' => $cart->cart_id,
                    'user' => $cart->user ? [
                        'user_id' => $cart->user->user_id,
                        'name' => $cart->user->full_name,
                        'email' => $cart->user->email,
                    ] : null,
                    'item_count' => $cart->items->count(),
                    'latest_item_updated_at' => $latestItem ? $latestItem->updated_at->format('Y-m-d H:i:s') : null,
                    'minutes_since_update' => $latestItem ? now()->diffInMinutes($latestItem->updated_at) : null,
                    'is_abandoned' => $latestItem ? now()->diffInMinutes($latestItem->updated_at) >= 5 : false,
                ];
            });

        $notifications = DB::table('notifications')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type', 'notifiable_id', 'data', 'read_at', 'created_at']);

        return response()->json([
            'status' => 'success',
            'current_time' => now()->format('Y-m-d H:i:s'),
            'threshold_time' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'active_carts' => $carts,
            'recent_notifications' => $notifications,
        ]);
    });

    Route::get('/run-order-emails', function () {
        try {
            Artisan::call('app:send-order-emails');

            return response()->json(['status' => 'success', 'output' => Artisan::output()]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });

    Route::get('/pending-emails', function () {
        $orders = Order::where('email_sent', false)
            ->with('user:user_id,full_name,email')
            ->latest()
            ->limit(20)
            ->get(['order_id', 'order_code', 'user_id', 'grand_total', 'email_sent', 'fulfillment_status', 'created_at'])
            ->map(function ($order) {
                return [
                    'order_code' => $order->order_code,
                    'user' => $order->user ? $order->user->full_name.' ('.$order->user->email.')' : 'N/A',
                    'grand_total' => number_format($order->grand_total, 0, ',', '.').'đ',
                    'status' => $order->fulfillment_status,
                    'created_at' => $order->created_at->format('H:i:s d/m'),
                    'minutes_ago' => now()->diffInMinutes($order->created_at),
                    'ready_to_send' => now()->diffInMinutes($order->created_at) >= 5,
                ];
            });

        return response()->json([
            'status' => 'success',
            'current_time' => now()->format('Y-m-d H:i:s'),
            'pending_orders' => $orders,
        ]);
    });
});

// FIX C6: image-proxy — chống path traversal, whitelist extensions, nosniff header
Route::get('image-proxy', function (Request $request) {
    $path = $request->query('path');
    if (! $path) {
        abort(404);
    }

    // Chặn path traversal sequences
    if (str_contains($path, '..') || str_contains($path, "\0")) {
        abort(403, 'Invalid path');
    }

    // Whitelist extensions cho phép
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (! in_array($extension, $allowedExtensions)) {
        abort(403, 'File type not allowed');
    }

    // Resolve absolute path và verify nằm trong storage boundary
    $storagePath = realpath(storage_path('app/public'));
    $absolutePath = realpath(storage_path('app/public/'.$path));

    // realpath trả false nếu file không tồn tại
    if (! $absolutePath || ! str_starts_with($absolutePath, $storagePath.DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($absolutePath, [
        'X-Content-Type-Options' => 'nosniff',
    ]);
});
// COURT BOOKING ROUTES
// PUBLIC & USER ROUTES
Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/{id}', [CourtController::class, 'show']);
Route::get('/courts/{id}/availability', [CourtController::class, 'availability']);
Route::get('/court-services', [CourtController::class, 'publicServices']);

Route::middleware('auth:api,admin')->group(function () {
    Route::post('/court-bookings/lock', [CourtBookingController::class, 'lock'])->middleware('throttle:10,1');
    Route::post('/court-bookings/release-lock', [CourtBookingController::class, 'releaseLock']);
    Route::post('/court-bookings', [CourtBookingController::class, 'store']);
    Route::get('/court-bookings', [CourtBookingController::class, 'index']);
    Route::get('/court-bookings/{id}', [CourtBookingController::class, 'show']);
    Route::post('/court-bookings/{id}/cancel', [CourtBookingController::class, 'cancel']);
    Route::post('/court-bookings/{id}/payments', [CourtBookingController::class, 'pay']);
    Route::get('/court-bookings/{id}/qr', [CourtBookingController::class, 'qr']);
});

// ADMIN ROUTES
Route::middleware(['auth:api,admin', 'role:admin,staff,seller'])->prefix('admin')->group(function () {
    // Courts Management
    Route::apiResource('courts', CourtAdminController::class);
    Route::apiResource('court-schedules', CourtScheduleAdminController::class);
    Route::apiResource('court-prices', CourtPriceAdminController::class);
    Route::apiResource('court-services', CourtServiceAdminController::class);
    Route::apiResource('court-maintenances', CourtMaintenanceAdminController::class);

    // Bookings Management
    Route::get('/court-bookings/conflicts', [CourtBookingAdminController::class, 'checkConflicts']);
    Route::apiResource('court-bookings', CourtBookingAdminController::class);
    Route::post('/court-bookings/{id}/split-payment', [CourtBookingAdminController::class, 'splitPayment']);
    Route::post('/court-bookings/{id}/check-in', [CourtBookingAdminController::class, 'checkIn']);
    Route::post('/court-bookings/{id}/check-out', [CourtBookingAdminController::class, 'checkOut']);
    Route::post('/court-bookings/{id}/services', [CourtBookingAdminController::class, 'addService']);
    Route::post('/court-bookings/{id}/extend', [CourtBookingAdminController::class, 'extend']);
    Route::post('/court-bookings/{id}/confirm', [CourtBookingAdminController::class, 'confirm']);
    Route::post('/court-bookings/{id}/cancel', [CourtBookingAdminController::class, 'cancel']);
    Route::post('/court-bookings/{id}/payments', [CourtBookingAdminController::class, 'recordPayment']);
    Route::post('/court-bookings/{id}/qr-check-in', [CourtBookingAdminController::class, 'qrCheckIn']);
    Route::get('/courts-calendar', [CourtBookingAdminController::class, 'calendar']);
    Route::get('/courts-dashboard', [CourtBookingAdminController::class, 'dashboard']);
    Route::get('/courts-stats', [CourtBookingAdminController::class, 'stats']);
});

// ==========================================
// GHN Integration routes
// GHN Webhook — server-to-server, xác thực token/HMAC + IP whitelist qua middleware
Route::middleware(['throttle:120,1', 'carrier.webhook:ghn'])
    ->post('/ghn-webhook', [GhnWebhookController::class, 'handle']);

// Ocean Express Webhook — server-to-server, xác thực token/HMAC + IP whitelist qua middleware
Route::middleware(['throttle:120,1', 'carrier.webhook:ocean_express'])
    ->post('/ocean-express-webhook', [OceanExpressWebhookController::class, 'handle']);
