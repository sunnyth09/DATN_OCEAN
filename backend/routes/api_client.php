<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['api', 'auth:api,admin']]);

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Api\Client\TrackingController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GhnController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PostCategoryController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductCommentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TestCorsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TryOnController;
use App\Http\Controllers\UserBankAccountController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletDepositController;
use App\Models\Cart;
use App\Services\FcmService;

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
Route::middleware('throttle:strict_api')->post('/register', [AuthController::class, 'register']);
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
Route::post('/auth/google/mobile', [AuthController::class, 'googleMobileLogin']);
Route::post('/auth/facebook/callback', [AuthController::class, 'facebookCallback']);
Route::middleware('throttle:20,1')->post('/refresh', [AuthController::class, 'refresh']);

// Try-on (Public - guest can use)
Route::get('/test-cors', [TestCorsController::class, 'test']);
Route::middleware('throttle:strict_api')->post('/try-on', [TryOnController::class, 'process']);
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
        Route::get('/admin/posts', [PostController::class, 'adminIndex']);
        Route::delete('/admin/posts/{id}', [PostController::class, 'destroy']);
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
        Route::middleware('throttle:strict_api')->get('/addresses', [AddressController::class, 'index']);
        Route::middleware('throttle:5,1')->post('/addresses', [AddressController::class, 'store']);
        Route::middleware('throttle:strict_api')->put('/addresses/{id}', [AddressController::class, 'update']);
        Route::middleware('throttle:strict_api')->delete('/addresses/{id}', [AddressController::class, 'destroy']);
        Route::middleware('throttle:strict_api')->put('/addresses/{id}/default', [AddressController::class, 'setDefault']);
    });

    // Coupons (Lưu và xem mã giảm giá của tôi)
    Route::middleware('customer.only')->group(function () {
        Route::middleware('throttle:strict_api')->get('/coupons', [CouponController::class, 'getUserCoupons']);
        Route::middleware('throttle:strict_api')->post('/coupons/save', [CouponController::class, 'saveCoupon']);
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
    Route::middleware(['throttle:5,1', 'profanity'])->post('/orders/feedback/batch', [ProductCommentController::class, 'storeBatch']);

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
        Route::middleware('throttle:strict_api')->post('/affiliate/register', [AffiliateController::class, 'register']);
        Route::middleware('throttle:strict_api')->get('/affiliate/profile', [AffiliateController::class, 'profile']);
        Route::middleware('throttle:strict_api')->get('/affiliate/statistics', [AffiliateController::class, 'statistics']);
        Route::middleware('throttle:strict_api')->get('/affiliate/conversions', [AffiliateController::class, 'conversions']);
        Route::middleware('throttle:3,60')->post('/affiliate/withdrawals', [AffiliateController::class, 'requestWithdrawal']);
        Route::middleware('throttle:strict_api')->get('/affiliate/withdrawals', [AffiliateController::class, 'withdrawals']);
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
    Route::middleware('throttle:strict_api')->post('/items', [CartController::class, 'addItem']);
    Route::middleware('throttle:120,1')->put('/items/{id}', [CartController::class, 'updateItem']);
    Route::middleware('throttle:120,1')->put('/items/{id}/variant', [CartController::class, 'changeVariant']);
    Route::delete('/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clearCart']);
    Route::post('/buy-again/{orderId}', [CartController::class, 'buyAgain']);
    Route::middleware('throttle:strict_api')->put('/select-all', [CartController::class, 'selectAll']);
    Route::post('/sync', [CartController::class, 'sync']);
});

Route::post('/cart/guest-details', [CartController::class, 'getGuestDetails']);
Route::middleware('throttle:30,1')->post('/orders/guest', [OrderController::class, 'storeGuest']);
Route::middleware('throttle:120,1')->get('/orders/status/{order_code}', [OrderController::class, 'publicStatus']);
Route::middleware('throttle:strict_api')->get('/tracking/{token}', [OrderTrackingController::class, 'trackByToken']);
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
Route::middleware(['auth:api,admin', 'throttle:strict_api'])->post('flash-sale/buy', [FlashSaleController::class, 'buy']);

// ==========================================
// AFFILIATE — Track Click (Public, không cần auth)
// ==========================================
Route::middleware('throttle:30,1')->post('/affiliate/track-click', [AffiliateController::class, 'trackClick']);

// ==========================================
// Business routes
// Public resources (Chỉ cho phép GET public, các thao tác khác cần admin)
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('products/home/best-selling', [ProductController::class, 'bestSelling']);
Route::get('products/home/on-sale', [ProductController::class, 'onSale']);
Route::get('products/import-template', [ProductController::class, 'downloadTemplate'])->middleware(['auth:api,admin', 'role:admin,staff']);
// Khai báo trước products/{id} để tránh route shadowing (Laravel khớp từ trên xuống)
Route::get('products/export', [ProductController::class, 'exportExcel'])->middleware(['auth:api,admin', 'role:admin,staff']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/slug/{slug}', [ProductController::class, 'show']);
Route::get('products/{id}/variants', [ProductController::class, 'getVariants']);
Route::get('products/{slug}/related', [ProductController::class, 'related']);
Route::get('products/{slug}/matching', [ProductController::class, 'matching']);
Route::get('products/{product_id}/comments', [ProductCommentController::class, 'getByProduct']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('productFeatured', [ProductController::class, 'productFeatured']);

// ==========================================
Route::get('productsAll', [ProductController::class, 'all']);
Route::get('productsFeatured', [ProductController::class, 'productFeatured']);

Route::get('brands', [BrandController::class, 'index']);

// Coupons (Công khai)
Route::get('coupons/public', [CouponController::class, 'getPublicCoupons']);
Route::post('coupons/check', [CouponController::class, 'checkCoupon']);

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
    Route::get('/lucky-wheel', [LoyaltyController::class, 'luckyWheelPrizes']);   // Danh sách quà vòng quay
    Route::post('/lucky-wheel/spin', [LoyaltyController::class, 'spinLuckyWheel']); // Quay vòng quay
    Route::get('/summary', [LoyaltyController::class, 'summary']);        // Điểm hiện tại + thống kê
    Route::post('/check-in', [LoyaltyController::class, 'checkIn']);      // Điểm danh
    Route::get('/history', [LoyaltyController::class, 'history']);        // Lịch sử giao dịch
    Route::middleware('throttle:strict_api')->post('/preview-burn', [LoyaltyController::class, 'previewBurn']); // Preview đổi điểm
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
    Route::post('/bank-accounts/verify', [UserBankAccountController::class, 'verifyAccount']);
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
Route::middleware(['auth:api', 'throttle:strict_api'])->prefix('chatbot')->group(function () {
    Route::post('/cart/add', [ChatbotController::class, 'addToCart']);
    Route::get('/addresses', [ChatbotController::class, 'getAddresses']);
    Route::post('/order/prepare', [ChatbotController::class, 'prepareOrder']);
    Route::post('/order/confirm', [ChatbotController::class, 'confirmOrder']);
    Route::post('/quick-order', [ChatbotController::class, 'quickOrder']);
    Route::post('/preferences', [ChatbotController::class, 'updatePreferences']);
});

// Live Chat (Realtime - Public/User)
Route::middleware('throttle:strict_api')->group(function () {
    Route::post('/live-chat/init', [ChatController::class, 'initSession']);
    Route::post('/live-chat/message', [ChatController::class, 'sendMessage']);
});

Route::prefix('ghn')->group(function () {
    Route::middleware('throttle:120,1')->post('/calculate-fee', [GhnController::class, 'calculateFee']);
    Route::middleware('throttle:120,1')->post('/leadtime', [GhnController::class, 'getLeadtime']);
});

Route::middleware('auth:api,admin')->prefix('ghn')->group(function () {
    Route::post('/order-detail', [GhnController::class, 'orderDetail']);
    Route::post('/cancel-order', [GhnController::class, 'cancelOrder']);
    Route::post('/print-label', [GhnController::class, 'printLabel']);
});
