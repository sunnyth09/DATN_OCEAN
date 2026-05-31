<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['api', 'auth:api,admin']]);
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PostCategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductCommentController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AdminAffiliateController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\TryOnController;

// Add this line to run the route: http://localhost:8000/api
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Welcome to the API!'
    ]);
});

// Auth routes (Public) — có Rate Limiting + Turnstile
Route::middleware('throttle:20,1')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:10,1')->post('/register', [AuthController::class, 'register']);
Route::post('/SubmitContact', [ContactController::class, 'SubmitContact']);
Route::post('/SubmitContactEmail', [ContactController::class, 'SubmitContactEmail']);

// Forgot Password routes (Public) — có Rate Limiting cho send OTP
Route::middleware('throttle:3,1')->post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::middleware('throttle:5,1')->post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword']);

// OAuth callbacks (Public)
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/facebook/callback', [AuthController::class, 'facebookCallback']);

// Auth routes (Protected - cần JWT token)
Route::middleware('auth:api,admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', function (Request $request) {
        return auth('admin')->user() ?? auth('api')->user();
    });
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::get('products/edit/{id}', [ProductController::class, 'edit']);

    // Post categories routes
    Route::get('/post-categories', [PostCategoryController::class, 'index']);
    Route::post('/post-categories', [PostCategoryController::class, 'create']);
    Route::put('/post-categories/{id}', [PostCategoryController::class, 'edit']);
    Route::delete('/post-categories/{id}', [PostCategoryController::class, 'destroy']);

    // Posts routes
    Route::post('/posts', [PostController::class, 'create']);
    Route::post('/posts/upload-image', [PostController::class, 'uploadImage']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::get('posts/edit/{id}', [PostController::class, 'edit']);

    // Try-on
    Route::middleware('throttle:10,1')->post('/try-on', [TryOnController::class, 'process']);

    // Return requests
    Route::post('/orders/{order}/return-request', [ReturnRequestController::class, 'store']);
    Route::get('/my/return-requests', [ReturnRequestController::class, 'myIndex']);
    Route::get('/my/return-requests/{id}', [ReturnRequestController::class, 'myShow']);
});

// Customer Profile routes (Protected - cần JWT token user/admin)
Route::middleware('auth:api,admin')->prefix('profile')->group(function () {
    Route::post('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'changePassword']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);
    Route::put('/addresses/{id}/default', [AddressController::class, 'setDefault']);


    // Coupons (Lưu và xem mã giảm giá của tôi)
    Route::get('/coupons', [CouponController::class, 'getUserCoupons']);
    Route::post('/coupons/save', [CouponController::class, 'saveCoupon']);

    // Đơn hàng của tôi
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order_code}/order-id', [OrderController::class, 'getOrderIdByCode']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    // Đánh giá sản phẩm
    Route::post('/orders/feedback', [ProductCommentController::class, 'store']);

    // ── Notifications (Thông báo inbox) ──
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // ── Reward Points (Điểm thưởng) ──
    Route::get('/reward-points', [NotificationController::class, 'rewardPoints']);
    // Wishlist (Sản phẩm yêu thích)
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::get('/favorites/ids', [FavoriteController::class, 'getFavoriteIds']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);

    // ── Affiliate (Hoa hồng giới thiệu) ──
    Route::post('/affiliate/register', [AffiliateController::class, 'register']);
    Route::get('/affiliate/profile', [AffiliateController::class, 'profile']);
    Route::get('/affiliate/statistics', [AffiliateController::class, 'statistics']);
    Route::get('/affiliate/conversions', [AffiliateController::class, 'conversions']);
    Route::post('/affiliate/withdrawals', [AffiliateController::class, 'requestWithdrawal']);
    Route::get('/affiliate/withdrawals', [AffiliateController::class, 'withdrawals']);
});

// Cart routes (Protected - cần JWT token user/admin)
Route::middleware('auth:api,admin')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'getCart']);
    Route::get('/count', [CartController::class, 'getCount']);
    Route::get('/upsell-suggestions', [CartController::class, 'upsellSuggestions']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::put('/items/{id}', [CartController::class, 'updateItem']);
    Route::put('/items/{id}/variant', [CartController::class, 'changeVariant']);
    Route::delete('/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clearCart']);
    Route::post('/buy-again/{orderId}', [CartController::class, 'buyAgain']);
});

// ==========================================
// FLASH SALE routes
// ==========================================
// Public — Danh sách Flash Sale đang active / upcoming
Route::get('flash-sale', [FlashSaleController::class, 'index']);
// Public — Tồn kho hiện tại (cho Progress Bar, poll mỗi 30s)
// ⚠️ Đặt TRƯỚC flash-sale/buy để tránh conflict {id} với 'buy'
Route::get('flash-sale/{id}/stock', [FlashSaleController::class, 'stock']);
// Protected — Mua Flash Sale (throttle 10 request/phút/user)
Route::middleware(['auth:api,admin', 'throttle:10,1'])->post('flash-sale/buy', [FlashSaleController::class, 'buy']);

// ==========================================
// AFFILIATE — Track Click (Public, không cần auth)
// ==========================================
Route::post('/affiliate/track-click', [AffiliateController::class, 'trackClick']);

// ==========================================
// NHÓM 1: QUAN TRỊ VIÊN CẤP CAO (admin)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin'])->prefix('admin')->group(function () {
    // Quản lý Khách hàng (Thêm/Sửa/Xóa/Phân quyền)
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus']);

    // Quản lý Nhân sự (Chỉ Admin)
    Route::get('/staff', [AdminStaffController::class, 'index']);
    Route::post('/staff', [AdminStaffController::class, 'store']);
    Route::put('/staff/{id}', [AdminStaffController::class, 'update']);
    Route::put('/staff/{id}/role', [AdminStaffController::class, 'updateRole']);
    Route::put('/staff/{id}/status', [AdminStaffController::class, 'updateStatus']);
    Route::delete('/staff/{id}', [AdminStaffController::class, 'destroy']);

    // Quản lý Liên hệ (Xóa)
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);

    // Quản lý Mã giảm giá (Chỉ Admin tạo/sửa/xóa)
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::put('/coupons/{id}', [CouponController::class, 'update']);
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
    Route::get('/coupons/{id}/usages', [CouponController::class, 'getCouponUsages']);

    // Flash Sale Management (Admin only)
    Route::post('/flash-sale', [FlashSaleController::class, 'store']);
    Route::post('/flash-sale/{id}/initialize', [FlashSaleController::class, 'initialize']);

    // ── Affiliate Management (Admin) ──
    Route::get('/affiliate/conversions', [AdminAffiliateController::class, 'conversions']);
    Route::put('/affiliate/conversions/{id}/approve', [AdminAffiliateController::class, 'approveConversion']);
    Route::put('/affiliate/conversions/{id}/cancel', [AdminAffiliateController::class, 'cancelConversion']);
    Route::get('/affiliate/withdrawals', [AdminAffiliateController::class, 'withdrawals']);
    Route::put('/affiliate/withdrawals/{id}/approve', [AdminAffiliateController::class, 'approveWithdrawal']);
    Route::put('/affiliate/withdrawals/{id}/reject', [AdminAffiliateController::class, 'rejectWithdrawal']);
    Route::put('/affiliate/withdrawals/{id}/paid', [AdminAffiliateController::class, 'markPaidWithdrawal']);

    // Quản lý Vị trí Làm việc (Admin only)
    Route::get('/work-locations', [\App\Http\Controllers\WorkLocationController::class, 'index']);
    Route::post('/work-locations', [\App\Http\Controllers\WorkLocationController::class, 'store']);
    Route::put('/work-locations/{id}', [\App\Http\Controllers\WorkLocationController::class, 'update']);
    Route::delete('/work-locations/{id}', [\App\Http\Controllers\WorkLocationController::class, 'destroy']);

    // Quản lý Ca Làm việc (Admin only)
    Route::get('/work-shifts', [\App\Http\Controllers\WorkShiftController::class, 'index']);
    Route::post('/work-shifts', [\App\Http\Controllers\WorkShiftController::class, 'store']);
    Route::put('/work-shifts/{id}', [\App\Http\Controllers\WorkShiftController::class, 'update']);
    Route::delete('/work-shifts/{id}', [\App\Http\Controllers\WorkShiftController::class, 'destroy']);

    // Phân Ca cho Nhân viên (Admin only)
    Route::get('/shift-assignments', [\App\Http\Controllers\WorkShiftController::class, 'getAssignments']);
    Route::post('/shift-assignments', [\App\Http\Controllers\WorkShiftController::class, 'saveAssignments']);

    // Flag chấm công bất thường (Admin only)
    Route::put('/attendance/{id}/flag', [\App\Http\Controllers\AttendanceController::class, 'flag']);

    // Return requests (Admin only)
    Route::get('/return-requests', [ReturnRequestController::class, 'adminIndex']);
    Route::get('/return-requests/{id}', [ReturnRequestController::class, 'adminShow']);
    Route::patch('/return-requests/{id}/approve', [ReturnRequestController::class, 'approve']);
    Route::patch('/return-requests/{id}/reject', [ReturnRequestController::class, 'reject']);
    Route::patch('/return-requests/{id}/received', [ReturnRequestController::class, 'received']);
    Route::patch('/return-requests/{id}/refund', [ReturnRequestController::class, 'refund']);
});

// ==========================================
// NHÓM 2: BÁN HÀNG & CHĂM SÓC KHÁCH HÀNG (admin, seller)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,seller'])->prefix('admin')->group(function () {
    // Quản lý Khách hàng (Chỉ Xem)
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);

    // Quản lý Đánh giá sản phẩm (Duyệt)
    Route::get('/reviews', [ProductCommentController::class, 'adminIndex']);
    Route::put('/reviews/{id}/approve', [ProductCommentController::class, 'approve']);
    Route::put('/reviews/{id}/reject', [ProductCommentController::class, 'reject']);
    Route::delete('/reviews/{id}', [ProductCommentController::class, 'destroy']);

    // Quản lý Liên hệ (Xem và trả lời)
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply']);

    // Quản lý Đơn hàng
    Route::get('/orders', [\App\Http\Controllers\AdminOrderController::class, 'index']);
    Route::put('/orders/bulk-status', [\App\Http\Controllers\AdminOrderController::class, 'bulkUpdateStatus']);
    Route::get('/orders/{id}', [\App\Http\Controllers\AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [\App\Http\Controllers\AdminOrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/ghn-sync', [\App\Http\Controllers\AdminOrderController::class, 'syncGHN']);

    // POS - Bán hàng trực tiếp
    Route::get('/pos/products/search', [PosController::class, 'searchProducts']);
    Route::get('/pos/products/scan', [PosController::class, 'scanProduct']);
    Route::post('/pos/mobile-scan', [PosController::class, 'mobileScan']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::get('/pos/orders/{id}/receipt-pdf', [PosController::class, 'exportReceiptPdf']);

    // Admin Live Chat
    Route::get('/live-chats', [\App\Http\Controllers\Admin\AdminChatController::class, 'getSessions']);
    Route::get('/live-chats/{id}', [\App\Http\Controllers\Admin\AdminChatController::class, 'getMessages']);
    Route::post('/live-chats/{id}/reply', [\App\Http\Controllers\Admin\AdminChatController::class, 'replyMessage']);
    Route::post('/live-chats/{id}/close', [\App\Http\Controllers\Admin\AdminChatController::class, 'closeSession']);
});

// ==========================================
// NHÓM 3: CHẤM CÔNG (Tất cả nhân viên hệ thống)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,seller,staff'])->prefix('admin')->group(function () {
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index']);
    Route::middleware('throttle:10,1')->post('/attendance/check-in', [\App\Http\Controllers\AttendanceController::class, 'checkIn']);
    Route::middleware('throttle:10,1')->post('/attendance/check-out', [\App\Http\Controllers\AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [\App\Http\Controllers\AttendanceController::class, 'today']);
    Route::get('/attendance/my-history', [\App\Http\Controllers\AttendanceController::class, 'myHistory']);

    // Tổng quan (Dashboard)
    Route::get('/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'getDashboardData']);
    
    // Admin Statistics (Detailed dashboard)
    Route::get('/statistics/overview', [\App\Http\Controllers\AdminStatisticsController::class, 'getOverview']);
    Route::get('/statistics/revenue', [\App\Http\Controllers\AdminStatisticsController::class, 'getRevenueChart']);
    Route::get('/statistics/orders-status', [\App\Http\Controllers\AdminStatisticsController::class, 'getOrderStatusChart']);
    Route::get('/statistics/top-products', [\App\Http\Controllers\AdminStatisticsController::class, 'getTopProducts']);
    Route::get('/statistics/top-customers', [\App\Http\Controllers\AdminStatisticsController::class, 'getTopCustomers']);
    Route::get('/statistics/report', [\App\Http\Controllers\AdminStatisticsController::class, 'getRevenueReport']);
    Route::get('/statistics/export-revenue-last-month', [\App\Http\Controllers\AdminStatisticsController::class, 'exportLastMonthRevenue']);
});


// ==========================================
// NHÓM KHO / IMPORT (Khai báo trước các route động như products/{id} để tránh shadowing)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,staff'])->group(function () {
    Route::post('products/import', [ProductController::class, 'importExcel']);
    Route::post('products/import/process-chunk', [ProductController::class, 'processImportChunk']);
    Route::get('products/import-template', [ProductController::class, 'downloadTemplate']);
});

// Business routes
// Public resources (Chỉ cho phép GET public, các thao tác khác cần admin)
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('products/{id}/variants', [ProductController::class, 'getVariants']);
Route::get('products/slug/{slug}', [ProductController::class, 'show']);
Route::get('products/{slug}/related', [ProductController::class, 'related']);
Route::get('products/{product_id}/comments', [ProductCommentController::class, 'getByProduct']);
Route::get('productFeatured', [ProductController::class, 'productFeatured']);

// ==========================================
// NHÓM 4: QUẢN LÝ KHO (admin, staff)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,staff'])->group(function () {
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{id}', [CategoryController::class, 'update']); // POST for multipart/form-data
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::delete('categories/{id}/image', [CategoryController::class, 'deleteImage']);

    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{id}', [ProductController::class, 'update']); // Use POST for multipart/form-data with _method=PUT
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
    Route::put('products/{id}/restore', [ProductController::class, 'restore']);
});

Route::get('productsAll', [ProductController::class, 'all']);
Route::get('productsFeatured', [ProductController::class, 'productFeatured']);

Route::get('brands', [BrandController::class, 'index']);

// Coupons (Công khai)
Route::get('coupons/public', [CouponController::class, 'getPublicCoupons']);

// API Địa chỉ Việt Nam (Public)
Route::prefix('location')->group(function () {
    Route::get('/provinces', [LocationController::class, 'getProvinces']);
    Route::get('/districts/{provinceCode}', [LocationController::class, 'getDistricts']);
    Route::get('/wards/{districtCode}', [LocationController::class, 'getWards']);
    Route::get('/search', [LocationController::class, 'search']);
});
Route::get('/posts', [PostController::class, 'index']);

// AI Chatbot (Public — tự detect auth nếu có JWT token)
Route::post('/chatbot/message', [\App\Http\Controllers\ChatbotController::class, 'sendMessage']);

// Live Chat (Realtime - Public/User)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/live-chat/init', [ChatController::class, 'initSession']);
    Route::post('/live-chat/message', [ChatController::class, 'sendMessage']);
});

// VNPay Payment Gateway (Public — VNPay redirect về đây, rate limiting chống brute-force)
Route::middleware('throttle:30,1')->get('/payment/vnpay-return', [\App\Http\Controllers\VNPayController::class, 'vnpayReturn']);
Route::middleware('throttle:30,1')->post('/payment/vnpay-ipn', [\App\Http\Controllers\VNPayController::class, 'vnpayIpn']);

// MoMo Payment Gateway
Route::middleware('throttle:30,1')->get('/payment/momo-return', [\App\Http\Controllers\MoMoController::class, 'momoReturn']);

Route::post('/payment/momo-ipn', [\App\Http\Controllers\MoMoController::class, 'momoIpn']);
// =====================================================================
// ██ DEBUG ROUTES — Chạy thủ công scheduler commands (XÓA KHI PRODUCTION)
// =====================================================================
Route::prefix('debug')->group(function () {
    // Test: Chạy abandoned cart command ngay lập tức
    // GET /api/debug/run-abandoned-cart
    Route::get('/run-abandoned-cart', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:remind-abandoned-cart');
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'status' => 'success',
                'message' => 'Command executed!',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    });

    // Test: Chạy birthday command ngay lập tức
    // GET /api/debug/run-birthday
    Route::get('/run-birthday', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:send-birthday-wishes');
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'status' => 'success',
                'message' => 'Command executed!',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    });

    // Test: Xem trạng thái cart + notification
    // GET /api/debug/cart-status
    Route::get('/cart-status', function () {
        $carts = \App\Models\Cart::where('status', 'active')
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
                        'reward_points' => $cart->user->reward_points,
                    ] : null,
                    'item_count' => $cart->items->count(),
                    'latest_item_updated_at' => $latestItem ? $latestItem->updated_at->format('Y-m-d H:i:s') : null,
                    'minutes_since_update' => $latestItem ? now()->diffInMinutes($latestItem->updated_at) : null,
                    'is_abandoned' => $latestItem ? now()->diffInMinutes($latestItem->updated_at) >= 5 : false,
                ];
            });

        $notifications = \Illuminate\Support\Facades\DB::table('notifications')
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

    // Test: Chạy send-order-emails ngay lập tức
    // GET /api/debug/run-order-emails
    Route::get('/run-order-emails', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:send-order-emails');
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'status' => 'success',
                'message' => 'Command executed!',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    });

    // Test: Xem đơn hàng chưa gửi email
    // GET /api/debug/pending-emails
    Route::get('/pending-emails', function () {
        $orders = \App\Models\Order::where('email_sent', false)
            ->with('user:user_id,full_name,email')
            ->latest()
            ->limit(20)
            ->get(['order_id', 'order_code', 'user_id', 'grand_total', 'email_sent', 'fulfillment_status', 'created_at'])
            ->map(function ($order) {
                return [
                    'order_code' => $order->order_code,
                    'user' => $order->user ? $order->user->full_name . ' (' . $order->user->email . ')' : 'N/A',
                    'grand_total' => number_format($order->grand_total, 0, ',', '.') . 'đ',
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

Route::get('image-proxy', function (\Illuminate\Http\Request $request) {
    $path = $request->query('path');
    if (!$path) abort(404);
    $absolutePath = storage_path('app/public/' . $path);
    if (!file_exists($absolutePath)) abort(404);
    return response()->file($absolutePath);
});
Route::middleware('throttle:30,1')->post('/payment/momo-ipn', [\App\Http\Controllers\MoMoController::class, 'momoIpn']);
