<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['api', 'auth:api,admin']]);

use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\AdminAffiliateController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStatisticsController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminWalletController;
use App\Http\Controllers\Api\Admin\RewardAdminController;
use App\Http\Controllers\Api\Admin\UserRewardAdminController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\FaceEncodingController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductCommentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\SizeGuideController;
use App\Http\Controllers\WorkLocationController;
use App\Http\Controllers\WorkShiftController;
use App\Models\Cart;
use App\Models\Order;
use App\Services\FcmService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// use Illuminate\Http\Request;


// ==========================================
// NHÓM 1: QUAN TRỊ VIÊN CẤP CAO (admin)
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
    Route::get('/reviews/pending-count', [ProductCommentController::class, 'pendingCount']);
    Route::get('/reviews', [ProductCommentController::class, 'adminIndex']);
    Route::put('/reviews/{id}/approve', [ProductCommentController::class, 'approve']);
    Route::put('/reviews/{id}/reject', [ProductCommentController::class, 'reject']);
    Route::delete('/reviews/{id}', [ProductCommentController::class, 'destroy']);

    // Quản lý Bình luận bài viết (Duyệt)
    Route::get('/post-comments', [PostCommentController::class, 'adminIndex']);
    Route::put('/post-comments/{id}/approve', [PostCommentController::class, 'approve']);
    Route::delete('/post-comments/{id}', [PostCommentController::class, 'destroy']);

    // Quản lý Liên hệ (Xem và trả lời)
    Route::get('/contacts/pending-count', [ContactController::class, 'pendingCount']);
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
    Route::post('/orders/{id}/self-delivery', [AdminOrderController::class, 'selfDelivery']);

    // POS - Bán hàng trực tiếp
    Route::get('/pos/products/search', [PosController::class, 'searchProducts']);
    Route::get('/pos/products/scan', [PosController::class, 'scanProduct']);
    Route::post('/pos/mobile-scan', [PosController::class, 'mobileScan']);
    Route::post('/pos/checkout', [PosController::class, 'checkout']);
    Route::get('/pos/orders/{id}/receipt-pdf', [PosController::class, 'exportReceiptPdf']);

    // Admin Live Chat
    Route::get('/live-chats/pending-count', [AdminChatController::class, 'getPendingCount']);
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
    Route::get('/statistics/slow-moving-products', [AdminStatisticsController::class, 'getSlowMovingProducts']);
    Route::get('/statistics/export-staff-sales', [AdminStatisticsController::class, 'exportStaffSales']);
    Route::get('/statistics/export-revenue-last-month', [AdminStatisticsController::class, 'exportLastMonthRevenue']);
});

// ==========================================
// NHÓM KHO / IMPORT (Khai báo trước các route động như products/{id} để tránh shadowing)
Route::middleware(['auth:api,admin', 'role:admin,staff'])->group(function () {
    Route::post('products/import', [ProductController::class, 'importExcel']);
    Route::post('products/import/process-chunk', [ProductController::class, 'processImportChunk']);
    Route::get('products/import-template', [ProductController::class, 'downloadTemplate']);
});

// ==========================================
// NHÓM 4: QUẢN LÝ KHO (admin, staff)
Route::middleware(['auth:api,admin', 'role:admin,staff'])->group(function () {
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{id}', [CategoryController::class, 'update']); // POST for multipart/form-data
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::delete('categories/{id}/image', [CategoryController::class, 'deleteImage']);

    // Quản lý Bảng Size
    Route::get('size-guides', [SizeGuideController::class, 'index']);
    Route::get('size-guides/{id}', [SizeGuideController::class, 'show']);
    Route::post('size-guides', [SizeGuideController::class, 'store']);
    Route::put('size-guides/{id}', [SizeGuideController::class, 'update']);
    Route::delete('size-guides/{id}', [SizeGuideController::class, 'destroy']);

    Route::post('products/upload-editor-image', [ProductController::class, 'uploadEditorImage']);
    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{id}', [ProductController::class, 'update']); // Use POST for multipart/form-data with _method=PUT
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
    Route::put('products/{id}/restore', [ProductController::class, 'restore']);
});
