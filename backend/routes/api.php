<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['api', 'auth:api,admin']]);

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\AdminAffiliateController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStatisticsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminWalletController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Api\Admin\CourtAdminController;
use App\Http\Controllers\Api\Admin\CourtBookingAdminController;
use App\Http\Controllers\Api\Admin\CourtMaintenanceAdminController;
use App\Http\Controllers\Api\Admin\CourtPriceAdminController;
use App\Http\Controllers\Api\Admin\CourtScheduleAdminController;
use App\Http\Controllers\Api\Admin\CourtServiceAdminController;
use App\Http\Controllers\Api\Admin\RewardAdminController;
use App\Http\Controllers\Api\Admin\UserRewardAdminController;
use App\Http\Controllers\Api\Client\TrackingController;
use App\Http\Controllers\Api\CourtBookingController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FaceEncodingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GhnController;
use App\Http\Controllers\GhnWebhookController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OceanExpressWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PostCategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductCommentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SepayController;
use App\Http\Controllers\TestCorsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TryOnController;
use App\Http\Controllers\UserBankAccountController;
use App\Http\Controllers\VNPayController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletDepositController;
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\WorkShiftController;
use App\Models\Cart;
use App\Models\Order;
use App\Services\FcmService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// use Illuminate\Http\Request;

// Route debug gửi push — CHỈ đăng ký ở môi trường local để tránh bị lạm dụng
// (spam FCM / dò token hợp lệ) trên production. Bọc thêm throttle phòng hờ.
if (app()->environment('local')) {
    Route::post('/test-push', function (Request $request, FcmService $fcm) {
        $token = $request->input('token');

        $success = $fcm->sendNotification(
            $token,
            '🚀 Test Thông Báo!',
            'Nếu bạn thấy dòng này, Laravel và Firebase đã thông nhau!',
            ['screen' => 'home'] // Data gửi kèm
        );

        return response()->json(['success' => $success]);
    })->middleware('throttle:5,1');
}
// API root health response.
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Welcome to the API!',
    ]);
});

// Auth routes (Public) — có Rate Limiting + Turnstile
Route::middleware('throttle:20,1')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:10,1')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/submitcontact', [ContactController::class, 'SubmitContact']);
    Route::post('/submitcontactemail', [ContactController::class, 'SubmitContactEmail']);
});

// Forgot Password routes (Public) — có Rate Limiting cho send OTP
Route::middleware('throttle:3,1')->post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
Route::middleware('throttle:5,1')->post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword']);

// OAuth callbacks (Public)
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/facebook/callback', [AuthController::class, 'facebookCallback']);
Route::middleware('throttle:20,1')->post('/refresh', [AuthController::class, 'refresh']);

// Try-on (Public - guest can use)
Route::get('/test-cors', [TestCorsController::class, 'test']);
Route::middleware('throttle:10,1')->post('/try-on', [TryOnController::class, 'process']);
Route::middleware('throttle:5,1')->post('/try-on/generate-360', [TryOnController::class, 'generate360Views']);

// Auth routes (Protected - cần JWT token)
Route::middleware('auth:api,admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
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

    // Posts routes (Admin & Staff only)
    Route::middleware('role:admin,staff')->group(function () {
        Route::post('/posts', [PostController::class, 'create']);
        Route::post('/posts/upload-image', [PostController::class, 'uploadImage']);
        Route::put('/posts/{id}', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);
        Route::get('posts/edit/{id}', [PostController::class, 'edit']);
    });

    // Return requests
    Route::post('/orders/{order}/return-request', [ReturnRequestController::class, 'store']);
    Route::get('/my/return-requests', [ReturnRequestController::class, 'myIndex']);
    Route::get('/my/return-requests/{id}', [ReturnRequestController::class, 'myShow']);

    // Device token
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

    // Loyalty (Tích điểm)
    Route::get('/loyalty/profile', [App\Http\Controllers\Api\LoyaltyController::class, 'profile']);
    Route::get('/loyalty/rewards', [App\Http\Controllers\Api\LoyaltyController::class, 'rewards']);
    Route::post('/loyalty/redeem', [App\Http\Controllers\Api\LoyaltyController::class, 'redeem']);

    // Chat (CSKH)
    Route::get('/chat/messages', [App\Http\Controllers\Api\ChatController::class, 'getMessages']);
    Route::post('/chat/messages', [App\Http\Controllers\Api\ChatController::class, 'sendMessage']);

    Route::post('/posts/{postId}/comments', [PostCommentController::class, 'store']);
});

// Customer Profile routes (Protected - cần JWT token user/admin)
Route::middleware('auth:api,admin')->prefix('profile')->group(function () {
    Route::post('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'changePassword']);

    Route::middleware('customer.only')->group(function () {
        Route::middleware('throttle:60,1')->get('/addresses', [AddressController::class, 'index']);
        Route::middleware('throttle:5,1')->post('/addresses', [AddressController::class, 'store']);
        Route::middleware('throttle:10,1')->put('/addresses/{id}', [AddressController::class, 'update']);
        Route::middleware('throttle:10,1')->delete('/addresses/{id}', [AddressController::class, 'destroy']);
        Route::middleware('throttle:10,1')->put('/addresses/{id}/default', [AddressController::class, 'setDefault']);
    });

    // Coupons (Lưu và xem mã giảm giá của tôi)
    Route::middleware('customer.only')->group(function () {
        Route::middleware('throttle:60,1')->get('/coupons', [CouponController::class, 'getUserCoupons']);
        Route::middleware('throttle:10,1')->post('/coupons/save', [CouponController::class, 'saveCoupon']);
    });

    // Đơn hàng của tôi
    Route::get('/orders', [OrderController::class, 'index']);
    Route::middleware('throttle:60,1')->post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order_code}/order-id', [OrderController::class, 'getOrderIdByCode']);
    Route::get('/orders/{id}/tracking', [OrderTrackingController::class, 'show']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    // Đánh giá sản phẩm
    Route::middleware(['throttle:5,1', 'profanity'])->post('/orders/feedback', [ProductCommentController::class, 'store']);

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
    Route::middleware('customer.only')->group(function () {
        Route::middleware('throttle:10,1')->post('/affiliate/register', [AffiliateController::class, 'register']);
        Route::middleware('throttle:60,1')->get('/affiliate/profile', [AffiliateController::class, 'profile']);
        Route::middleware('throttle:60,1')->get('/affiliate/statistics', [AffiliateController::class, 'statistics']);
        Route::middleware('throttle:60,1')->get('/affiliate/conversions', [AffiliateController::class, 'conversions']);
        Route::middleware('throttle:3,60')->post('/affiliate/withdrawals', [AffiliateController::class, 'requestWithdrawal']);
        Route::middleware('throttle:60,1')->get('/affiliate/withdrawals', [AffiliateController::class, 'withdrawals']);
    });
    // Khiếu nại của tôi
    Route::get('/tickets', [TicketController::class, 'clientIndex']);
    Route::middleware('throttle:3,1')->post('/tickets', [TicketController::class, 'clientStore']);
});

// Tracking routes (Public, optional auth logic handled inside controller)
Route::prefix('tracking')->group(function () {
    Route::middleware('throttle:120,1')->post('/view-product', [TrackingController::class, 'viewProduct']);
    Route::get('/recently-viewed', [TrackingController::class, 'getRecentlyViewed']);
    Route::get('/search-history', [TrackingController::class, 'getSearchHistory']);
});

// Cart routes (Protected - cần JWT token user/admin)
Route::middleware('auth:api,admin')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'getCart']);
    Route::get('/count', [CartController::class, 'getCount']);
    Route::get('/upsell-suggestions', [CartController::class, 'upsellSuggestions']);
    Route::middleware('throttle:60,1')->post('/items', [CartController::class, 'addItem']);
    Route::middleware('throttle:120,1')->put('/items/{id}', [CartController::class, 'updateItem']);
    Route::middleware('throttle:120,1')->put('/items/{id}/variant', [CartController::class, 'changeVariant']);
    Route::delete('/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clearCart']);
    Route::post('/buy-again/{orderId}', [CartController::class, 'buyAgain']);
    Route::middleware('throttle:60,1')->put('/select-all', [CartController::class, 'selectAll']);
    Route::post('/sync', [CartController::class, 'sync']);
});

Route::post('/cart/guest-details', [CartController::class, 'getGuestDetails']);
Route::middleware('throttle:30,1')->post('/orders/guest', [OrderController::class, 'storeGuest']);
Route::middleware('throttle:30,1')->get('/tracking/{token}', [OrderTrackingController::class, 'trackByToken']);
Route::post('/orders/guest-tracking', [OrderTrackingController::class, 'trackByPhone']);

// ==========================================
// FLASH SALE routes
// ==========================================
// Public — Danh sách Flash Sale đang active / upcoming
Route::get('flash-sale', [FlashSaleController::class, 'index']);
// Public — Tồn kho hiện tại (cho Progress Bar, poll mỗi 30s)
// ⚠️ Đặt TRƯỚC flash-sale/buy để tránh conflict {id} với 'buy'
Route::get('flash-sale/{id}/stock', [FlashSaleController::class, 'stock']);
// Protected — Mua Flash Sale (30 request/phút/user — đủ thoải mái thử lại)
Route::middleware(['auth:api,admin', 'throttle:30,1'])->post('flash-sale/buy', [FlashSaleController::class, 'buy']);

// ==========================================
// AFFILIATE — Track Click (Public, không cần auth)
// ==========================================
Route::middleware('throttle:30,1')->post('/affiliate/track-click', [AffiliateController::class, 'trackClick']);

// ==========================================
// NHÓM 1: QUAN TRỊ VIÊN CẤP CAO (admin)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin'])->prefix('admin')->group(function () {
    // Quản lý Loyalty & Quà tặng
    Route::get('/rewards', [RewardAdminController::class, 'index']);
    Route::post('/rewards', [RewardAdminController::class, 'store']);
    Route::get('/rewards/{id}', [RewardAdminController::class, 'show']);
    Route::put('/rewards/{id}', [RewardAdminController::class, 'update']);
    Route::delete('/rewards/{id}', [RewardAdminController::class, 'destroy']);

    Route::get('/user-rewards', [UserRewardAdminController::class, 'index']);
    Route::put('/user-rewards/{id}/status', [UserRewardAdminController::class, 'updateStatus']);

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

    // ── Combo/Bundle (Flash Sale Combo + Auto Voucher) ──
    Route::post('/combos/flash-sale', [ComboController::class, 'storeFlashCombo']);
    Route::post('/combos/voucher', [ComboController::class, 'storeComboVoucher']);

    // ── Loyalty (Admin) ──
    Route::get('/loyalty/rules', [LoyaltyController::class, 'adminListRules']);
    Route::put('/loyalty/rules/{key}', [LoyaltyController::class, 'adminUpdateRule']);
    Route::post('/loyalty/users/{userId}/adjust', [LoyaltyController::class, 'adminAdjust']);
    Route::get('/loyalty/users/{userId}/history', [LoyaltyController::class, 'adminUserHistory']);

    // ── Wallet (Ví cá nhân — Admin) ──
    Route::get('/wallets/deposits/pending', [AdminWalletController::class, 'pendingDeposits']);
    Route::post('/wallets/deposits/{depositId}/confirm', [AdminWalletController::class, 'confirmDeposit']);
    Route::post('/wallets/deposits/{depositId}/reject', [AdminWalletController::class, 'rejectDeposit']);
    Route::get('/wallets', [AdminWalletController::class, 'index']);
    Route::get('/wallets/{userId}', [AdminWalletController::class, 'show']);
    Route::post('/wallets/{userId}/adjust', [AdminWalletController::class, 'adjust']);

    // Duyệt rút tiền ví
    Route::get('/wallets/withdrawals/pending', [AdminWalletController::class, 'withdrawals']);
    Route::put('/wallets/withdrawals/{id}/complete', [AdminWalletController::class, 'completeWithdrawal']);
    Route::put('/wallets/withdrawals/{id}/reject', [AdminWalletController::class, 'rejectWithdrawal']);

    // Flash Sale Management (Admin only)
    Route::get('/flash-sale', [App\Http\Controllers\Admin\FlashSaleController::class, 'adminIndex']);
    Route::get('/flash-sale/search-products', [App\Http\Controllers\Admin\FlashSaleController::class, 'searchProducts']);
    Route::post('/flash-sale', [App\Http\Controllers\Admin\FlashSaleController::class, 'store']);
    Route::put('/flash-sale/{id}', [App\Http\Controllers\Admin\FlashSaleController::class, 'update']);
    Route::delete('/flash-sale/{id}', [App\Http\Controllers\Admin\FlashSaleController::class, 'destroy']);
    Route::post('/flash-sale/{id}/initialize', [App\Http\Controllers\Admin\FlashSaleController::class, 'initialize']);
    // ── Affiliate Management (Admin) ──
    Route::get('/affiliate/users', [AdminAffiliateController::class, 'affiliates']);
    Route::get('/affiliate/conversions', [AdminAffiliateController::class, 'conversions']);
    Route::put('/affiliate/conversions/{id}/approve', [AdminAffiliateController::class, 'approveConversion']);
    Route::put('/affiliate/conversions/{id}/cancel', [AdminAffiliateController::class, 'cancelConversion']);
    Route::get('/affiliate/withdrawals', [AdminAffiliateController::class, 'withdrawals']);
    Route::put('/affiliate/withdrawals/{id}/approve', [AdminAffiliateController::class, 'approveWithdrawal']);
    Route::put('/affiliate/withdrawals/{id}/reject', [AdminAffiliateController::class, 'rejectWithdrawal']);
    Route::put('/affiliate/withdrawals/{id}/paid', [AdminAffiliateController::class, 'markPaidWithdrawal']);

    // Quản lý Vị trí Làm việc (Admin only)
    Route::get('/work-locations', [WorkLocationController::class, 'index']);
    Route::post('/work-locations', [WorkLocationController::class, 'store']);
    Route::put('/work-locations/{id}', [WorkLocationController::class, 'update']);
    Route::delete('/work-locations/{id}', [WorkLocationController::class, 'destroy']);

    // Quản lý Ca Làm việc (Admin only)
    Route::get('/work-shifts', [WorkShiftController::class, 'index']);
    Route::post('/work-shifts', [WorkShiftController::class, 'store']);
    Route::put('/work-shifts/{id}', [WorkShiftController::class, 'update']);
    Route::delete('/work-shifts/{id}', [WorkShiftController::class, 'destroy']);

    // Phân Ca cho Nhân viên (Admin only)
    Route::get('/shift-assignments', [WorkShiftController::class, 'getAssignments']);
    Route::post('/shift-assignments', [WorkShiftController::class, 'saveAssignments']);

    // Flag chấm công bất thường (Admin only)
    Route::put('/attendance/{id}/flag', [AttendanceController::class, 'flag']);

    // Return requests (Admin only)
    Route::get('/return-requests', [ReturnRequestController::class, 'adminIndex']);
    Route::get('/return-requests/{id}', [ReturnRequestController::class, 'adminShow']);
    Route::patch('/return-requests/{id}/approve', [ReturnRequestController::class, 'approve']);
    Route::patch('/return-requests/{id}/reject', [ReturnRequestController::class, 'reject']);
    Route::patch('/return-requests/{id}/returning', [ReturnRequestController::class, 'returning']);
    Route::patch('/return-requests/{id}/received', [ReturnRequestController::class, 'received']);
    Route::patch('/return-requests/{id}/inspect', [ReturnRequestController::class, 'inspect']);
    Route::patch('/return-requests/{id}/refund', [ReturnRequestController::class, 'refund']);
});

// ==========================================
// NHÓM 2: BÁN HÀNG & CHĂM SÓC KHÁCH HÀNG (admin, seller)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,seller'])->prefix('admin')->group(function () {
    // Quản lý Khách hàng (Chỉ Xem)
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);

    // Admin Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\Admin\NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Api\Admin\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\Admin\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [App\Http\Controllers\Api\Admin\NotificationController::class, 'destroy']);

    // Quản lý Đánh giá sản phẩm (Duyệt)
    Route::get('/reviews', [ProductCommentController::class, 'adminIndex']);
    Route::put('/reviews/{id}/approve', [ProductCommentController::class, 'approve']);
    Route::put('/reviews/{id}/reject', [ProductCommentController::class, 'reject']);
    Route::delete('/reviews/{id}', [ProductCommentController::class, 'destroy']);

    // Quản lý Bình luận bài viết (Duyệt)
    Route::get('/post-comments', [PostCommentController::class, 'adminIndex']);
    Route::put('/post-comments/{id}/approve', [PostCommentController::class, 'approve']);
    Route::delete('/post-comments/{id}', [PostCommentController::class, 'destroy']);

    // Quản lý Liên hệ (Xem và trả lời)
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply']);

    // Quản lý Đơn hàng
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::put('/orders/bulk-status', [AdminOrderController::class, 'bulkUpdateStatus']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::put('/orders/{id}/force-status', [AdminOrderController::class, 'forceStatus']);
    Route::get('/orders/{id}/available-transitions', [AdminOrderController::class, 'availableTransitions']);
    Route::post('/orders/{id}/ghn-sync', [AdminOrderController::class, 'syncGHN']);

    // POS - Bán hàng trực tiếp
    Route::get('/pos/products/search', [PosController::class, 'searchProducts']);
    Route::get('/pos/products/scan', [PosController::class, 'scanProduct']);
    Route::post('/pos/mobile-scan', [PosController::class, 'mobileScan']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::get('/pos/orders/{id}/receipt-pdf', [PosController::class, 'exportReceiptPdf']);

    // Admin Live Chat
    Route::get('/live-chats', [AdminChatController::class, 'getSessions']);
    Route::get('/live-chats/{id}', [AdminChatController::class, 'getMessages']);
    Route::post('/live-chats/{id}/reply', [AdminChatController::class, 'replyMessage']);
    Route::post('/live-chats/{id}/close', [AdminChatController::class, 'closeSession']);

    // Quản lý Khiếu nại
    Route::get('/tickets', [TicketController::class, 'adminIndex']);
    Route::get('/tickets/{id}', [TicketController::class, 'adminShow']);
    Route::put('/tickets/{id}', [TicketController::class, 'adminUpdate']);
});

// ==========================================
// NHÓM 3: CHẤM CÔNG (Tất cả nhân viên hệ thống)
// ==========================================
Route::middleware(['auth:api,admin', 'role:admin,seller,staff'])->prefix('admin')->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::middleware('throttle:10,1')->post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::middleware('throttle:10,1')->post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/my-history', [AttendanceController::class, 'myHistory']);

    // Face Registration & Verification (tất cả nhân viên)
    Route::middleware('throttle:10,1')->post('/face/register', [FaceEncodingController::class, 'register']);
    Route::get('/face/status', [FaceEncodingController::class, 'status']);
    Route::delete('/face/{id}', [FaceEncodingController::class, 'destroy']);
    Route::post('/face/reset', [FaceEncodingController::class, 'reset']);

    // Face Management (admin only)
    Route::get('/face/management', [FaceEncodingController::class, 'management']);
    Route::post('/face/reset-user/{userId}', [FaceEncodingController::class, 'adminResetUser']);

    // Tổng quan (Dashboard)
    Route::get('/dashboard', [AdminDashboardController::class, 'getDashboardData']);
    Route::middleware('throttle:60,1')->get('/sidebar-badges', [AdminDashboardController::class, 'getSidebarBadges']);

    // Admin Statistics (Detailed dashboard)
    Route::get('/statistics/overview', [AdminStatisticsController::class, 'getOverview']);
    Route::get('/statistics/revenue', [AdminStatisticsController::class, 'getRevenueChart']);
    Route::get('/statistics/orders-status', [AdminStatisticsController::class, 'getOrderStatusChart']);
    Route::get('/statistics/top-products', [AdminStatisticsController::class, 'getTopProducts']);
    Route::get('/statistics/top-customers', [AdminStatisticsController::class, 'getTopCustomers']);
    Route::get('/statistics/report', [AdminStatisticsController::class, 'getRevenueReport']);
    Route::get('/statistics/staff-sales', [AdminStatisticsController::class, 'getStaffSales']);
    Route::get('/statistics/export-staff-sales', [AdminStatisticsController::class, 'exportStaffSales']);
    Route::get('/statistics/export-revenue-last-month', [AdminStatisticsController::class, 'exportLastMonthRevenue']);
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

// ==========================================
// COMBO / BUNDLE PROMOTION (Public)
// ==========================================
// Danh sách flash sale combo + combo voucher đang active
Route::get('/combos', [ComboController::class, 'index']);
// Kiểm tra cart eligible + preview discount (cần auth)
Route::middleware('auth:api')->post('/combos/check-cart', [ComboController::class, 'checkCart']);

// ==========================================
// LOYALTY (Điểm thưởng)
// ==========================================
// Quy tắc earn/burn (public — user xem)
Route::get('/loyalty/rules', [LoyaltyController::class, 'rules']);

// Routes yêu cầu đăng nhập
Route::middleware('auth:api')->prefix('loyalty')->group(function () {
    Route::get('/summary', [LoyaltyController::class, 'summary']);        // Điểm hiện tại + thống kê
    Route::get('/history', [LoyaltyController::class, 'history']);        // Lịch sử giao dịch
    Route::middleware('throttle:60,1')->post('/preview-burn', [LoyaltyController::class, 'previewBurn']); // Preview đổi điểm
});

// ==========================================
// WALLET (Ví cá nhân)
// ==========================================
Route::middleware('auth:api')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'index']);                  // Số dư + thống kê
    Route::get('/history', [WalletController::class, 'history']);          // Lịch sử giao dịch
    Route::get('/preview-discount', [WalletController::class, 'previewDiscount']); // Preview giảm giá checkout

    // Nạp tiền ví (15 requests/phút — cho phép retry khi gateway lỗi)
    Route::middleware('throttle:15,1')->post('/deposit/init', [WalletDepositController::class, 'initDeposit']);

    // Rút tiền ví (rate limited: 3 requests/phút)
    Route::middleware('throttle:3,1')->post('/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/withdrawals', [WalletController::class, 'withdrawals']);

    // Tài khoản ngân hàng liên kết
    Route::get('/bank-accounts', [UserBankAccountController::class, 'index']);
    Route::post('/bank-accounts', [UserBankAccountController::class, 'store']);
    Route::put('/bank-accounts/{id}', [UserBankAccountController::class, 'update']);
    Route::delete('/bank-accounts/{id}', [UserBankAccountController::class, 'destroy']);
    Route::post('/bank-accounts/{id}/default', [UserBankAccountController::class, 'setDefault']);
});

// API Địa chỉ Việt Nam (Public)
Route::prefix('location')->group(function () {
    Route::get('/provinces', [LocationController::class, 'getProvinces']);
    Route::get('/districts/{provinceCode}', [LocationController::class, 'getDistricts']);
    Route::get('/wards/{districtCode}', [LocationController::class, 'getWards']);
    Route::get('/search', [LocationController::class, 'search']);
});
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{idOrSlug}', [PostController::class, 'show']);
Route::get('/posts/{postId}/comments', [PostCommentController::class, 'getByPost']);

// AI Chatbot (Public — tự detect auth nếu có JWT token, có rate limit chống abuse AI)
Route::middleware('throttle:20,1')->post('/chatbot/message', [ChatbotController::class, 'sendMessage']);

// Chatbot transactional actions (Customer only — không cho admin/staff đặt hàng qua AI)
Route::middleware(['auth:api', 'throttle:30,1'])->prefix('chatbot')->group(function () {
    Route::post('/cart/add', [ChatbotController::class, 'addToCart']);
    Route::get('/addresses', [ChatbotController::class, 'getAddresses']);
    Route::post('/order/prepare', [ChatbotController::class, 'prepareOrder']);
    Route::post('/order/confirm', [ChatbotController::class, 'confirmOrder']);
    Route::post('/quick-order', [ChatbotController::class, 'quickOrder']);
    Route::post('/preferences', [ChatbotController::class, 'updatePreferences']);
});

// Live Chat (Realtime - Public/User)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/live-chat/init', [ChatController::class, 'initSession']);
    Route::post('/live-chat/message', [ChatController::class, 'sendMessage']);
});

// VNPay Payment Gateway (Public — VNPay redirect về đây)
// IPN là server-to-server từ VNPay — không throttle, return URL tăng lên 60 cho user retry
Route::middleware('throttle:60,1')->get('/payment/vnpay-return', [VNPayController::class, 'vnpayReturn']);
Route::post('/payment/vnpay-ipn', [VNPayController::class, 'vnpayIpn']);

// SePay Webhook — server-to-server, không throttle
Route::post('/payment/sepay-webhook', [SepayController::class, 'handleWebhook']);
// =====================================================================
// ██ DEBUG ROUTES — Chạy thủ công scheduler commands (XÓA KHI PRODUCTION)
// =====================================================================
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
// ==========================================
// COURT BOOKING ROUTES
// ==========================================
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

Route::prefix('ghn')->group(function () {
    Route::middleware('throttle:120,1')->post('/calculate-fee', [GhnController::class, 'calculateFee']);
    Route::middleware('throttle:120,1')->post('/leadtime', [GhnController::class, 'getLeadtime']);
});

Route::middleware('auth:api,admin')->prefix('ghn')->group(function () {
    Route::post('/order-detail', [GhnController::class, 'orderDetail']);
    Route::post('/cancel-order', [GhnController::class, 'cancelOrder']);
    Route::post('/print-label', [GhnController::class, 'printLabel']);
});
