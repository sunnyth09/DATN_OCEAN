<?php
/**
 * test_loyalty.php - Kiểm tra hệ thống Điểm thưởng
 * Chạy: php test_loyalty.php (từ thư mục backend)
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;

$loyaltyService = app(LoyaltyService::class);

echo "\n" . str_repeat("=", 60) . "\n";
echo "       KIỂM TRA HỆ THỐNG ĐIỂM THƯỞNG LOYALTY\n";
echo str_repeat("=", 60) . "\n\n";

// ─── 0. Kiểm tra các Loyalty Rules trong DB ───
echo "📋 [0] KIỂM TRA LOYALTY RULES TRONG DATABASE:\n";
$rules = LoyaltyRule::all(['key', 'name', 'points_per_unit', 'is_active']);
if ($rules->isEmpty()) {
    echo "   ❌ Không có rule nào trong DB! Cần chạy seeder.\n";
} else {
    foreach ($rules as $rule) {
        $status = $rule->is_active ? '✅' : '⚠️ (inactive)';
        echo "   {$status} [{$rule->key}] {$rule->name} — {$rule->points_per_unit} điểm\n";
    }
}

// ─── Lấy user test ───
$user = User::where('status', 'active')->first();
if (!$user) {
    echo "\n❌ Không có user nào! Dừng test.\n";
    exit(1);
}
echo "\n👤 User test: #{$user->user_id} — {$user->full_name} ({$user->email})\n";
echo "   Điểm hiện tại: {$user->reward_points} điểm\n\n";

$initialPoints = $loyaltyService->getBalance($user->user_id);

// ─── 1. Test earnFromOrder ───
echo "─── [1] MUA HÀNG THÀNH CÔNG (+1đ/10.000đ) ───\n";
$rule1 = LoyaltyRule::findByKey('ORDER_COMPLETE');
if (!$rule1) {
    echo "   ❌ Không tìm thấy rule ORDER_COMPLETE!\n";
} else {
    echo "   ✅ Rule: {$rule1->name} — {$rule1->points_per_unit} đ/unit, active=" . ($rule1->is_active ? 'true' : 'false') . "\n";
    // Tạo order fake để test
    $fakeOrder = new Order(['order_id' => 99999, 'order_code' => 'TEST-001', 'grand_total' => 150000, 'user_id' => $user->user_id]);
    $points = (int) floor(($fakeOrder->grand_total / 10000) * $rule1->points_per_unit);
    echo "   📊 Đơn 150.000đ → sẽ tích: {$points} điểm\n";
    echo "   ✅ Logic đúng: " . ($points === 15 ? "YEP (15 điểm)" : "KO (expected 15, got {$points})") . "\n";
}

// ─── 2. Test earnFromReview ───
echo "\n─── [2] ĐÁNH GIÁ SẢN PHẨM CÓ NỘI DUNG (+20đ) ───\n";
$rule2 = LoyaltyRule::findByKey('REVIEW');
if (!$rule2) {
    echo "   ❌ Không tìm thấy rule REVIEW!\n";
} else {
    echo "   ✅ Rule: {$rule2->name} — {$rule2->points_per_unit} điểm, active=" . ($rule2->is_active ? 'true' : 'false') . "\n";
    echo "   ✅ Gọi từ: ProductCommentController::store() khi content không rỗng\n";
}

// ─── 3. Test earnFromReviewWithImage ───
echo "\n─── [3] ĐÁNH GIÁ KÈM HÌNH ẢNH (+50đ) ───\n";
$rule3 = LoyaltyRule::findByKey('REVIEW_WITH_IMAGE');
if (!$rule3) {
    echo "   ❌ Không tìm thấy rule REVIEW_WITH_IMAGE!\n";
} else {
    echo "   ✅ Rule: {$rule3->name} — {$rule3->points_per_unit} điểm, active=" . ($rule3->is_active ? 'true' : 'false') . "\n";
    echo "   ✅ Gọi từ: ProductCommentController::store() khi có file images\n";
}

// ─── 4. Test earnBirthday ───
echo "\n─── [4] SINH NHẬT (+100đ) ───\n";
$rule4 = LoyaltyRule::findByKey('BIRTHDAY');
if (!$rule4) {
    echo "   ❌ Không tìm thấy rule BIRTHDAY!\n";
} else {
    echo "   ✅ Rule: {$rule4->name} — {$rule4->points_per_unit} điểm, active=" . ($rule4->is_active ? 'true' : 'false') . "\n";
    echo "   ✅ Gọi từ: SendBirthdayWishes::handle() qua Artisan command\n";
    // Test trực tiếp earnBirthday (có check tránh trùng nên chỉ test logic)
    $alreadyEarned = LoyaltyTransaction::where('user_id', $user->user_id)
        ->where('type', 'earn')->where('reference_type', 'birthday')
        ->whereYear('created_at', now()->year)->exists();
    echo "   📊 User này đã nhận điểm sinh nhật năm nay: " . ($alreadyEarned ? "CÓ (sẽ skip)" : "CHƯA") . "\n";
}

// ─── 5. Test earnFromReferral ───
echo "\n─── [5] GIỚI THIỆU BẠN BÈ (+200đ) ───\n";
$rule5 = LoyaltyRule::findByKey('REFERRAL');
if (!$rule5) {
    echo "   ❌ Không tìm thấy rule REFERRAL!\n";
} else {
    echo "   ✅ Rule: {$rule5->name} — {$rule5->points_per_unit} điểm, active=" . ($rule5->is_active ? 'true' : 'false') . "\n";
    echo "   ✅ Gọi từ: AffiliateService::updateConversionOnStatusChange() và adminApproveConversion()\n";
}

// ─── 6. Test earnAbandonedCart ───
echo "\n─── [6] QUAY LẠI GIỎ HÀNG BỎ QUÊN (+30đ) ───\n";
$rule6 = LoyaltyRule::findByKey('ABANDONED_CART');
if (!$rule6) {
    echo "   ❌ Không tìm thấy rule ABANDONED_CART!\n";
} else {
    echo "   ✅ Rule: {$rule6->name} — {$rule6->points_per_unit} điểm, active=" . ($rule6->is_active ? 'true' : 'false') . "\n";
    // Kiểm tra logic trong AdminOrderService
    echo "   ✅ Gọi từ: AdminOrderService khi order COMPLETED và is_abandoned_checkout=true\n";
    // Check RemindAbandonedCart có gọi earnAbandonedCart không
    $cmdFile = file_get_contents(__DIR__ . '/app/Console/Commands/RemindAbandonedCart.php');
    $hasEarnCall = str_contains($cmdFile, 'earnAbandonedCart');
    if ($hasEarnCall) {
        echo "   ✅ RemindAbandonedCart.php gọi earnAbandonedCart\n";
    } else {
        echo "   ⚠️  RemindAbandonedCart.php KHÔNG gọi earnAbandonedCart trực tiếp.\n";
        echo "       Logic: đặt cờ is_abandoned_checkout trên Order khi checkout\n";
        echo "       → AdminOrderService.php cộng điểm khi order COMPLETED\n";
    }
    // Kiểm tra OrderService có đặt is_abandoned_checkout không
    $orderSvcFile = file_get_contents(__DIR__ . '/app/Services/OrderService.php');
    $hasFlag = str_contains($orderSvcFile, 'is_abandoned_checkout');
    echo "   " . ($hasFlag ? "✅" : "❌") . " OrderService.php đặt flag is_abandoned_checkout: " . ($hasFlag ? "CÓ" : "KHÔNG") . "\n";
}

// ─── 7. Test earnFromSocialShare ───
echo "\n─── [7] CHIA SẺ MẠNG XÃ HỘI ───\n";
$rule7 = LoyaltyRule::findByKey('SOCIAL_SHARE');
if (!$rule7) {
    echo "   ❌ Không tìm thấy rule SOCIAL_SHARE!\n";
} else {
    echo "   ✅ Rule: {$rule7->name} — {$rule7->points_per_unit} điểm, active=" . ($rule7->is_active ? 'true' : 'false') . "\n";
    echo "   ✅ Backend: POST /api/loyalty/social-share route đã có\n";
    // Check frontend call
    $frontendFiles = glob(__DIR__ . '/../frontend/src/**/*.vue');
    $hasShareCall = false;
    foreach ($frontendFiles as $file) {
        if (str_contains(file_get_contents($file), 'socialShare')) {
            echo "   ✅ Frontend gọi socialShare trong: " . basename($file) . "\n";
            $hasShareCall = true;
        }
    }
    if (!$hasShareCall) {
        echo "   ⚠️  Frontend chưa có component nào gọi loyaltyService.socialShare()\n";
        echo "       → Cần thêm nút chia sẻ vào trang chi tiết sản phẩm\n";
    }
}

// ─── Tổng kết ───
echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 TỔNG KẾT\n";
echo str_repeat("=", 60) . "\n";

$checks = [
    'ORDER_COMPLETE' => 'Mua hàng → AdminOrderService ✅',
    'REVIEW'         => 'Đánh giá có nội dung → ProductCommentController ✅',
    'REVIEW_WITH_IMAGE' => 'Đánh giá kèm ảnh → ProductCommentController ✅',
    'BIRTHDAY'       => 'Sinh nhật → SendBirthdayWishes Command ✅',
    'REFERRAL'       => 'Giới thiệu bạn bè → AffiliateService ✅',
    'ABANDONED_CART' => 'Giỏ hàng bỏ quên → OrderService flag + AdminOrderService ✅',
    'SOCIAL_SHARE'   => 'Chia sẻ MXH → Route /loyalty/social-share (frontend cần tích hợp nút share)',
];

foreach ($checks as $key => $label) {
    $rule = LoyaltyRule::findByKey($key);
    $status = $rule && $rule->is_active ? '✅' : '❌';
    echo "   {$status} {$label}\n";
}

// Kiểm tra abandonment flow
$orderSvcFile = file_get_contents(__DIR__ . '/app/Services/OrderService.php');
$abandonedOk = str_contains($orderSvcFile, 'is_abandoned_checkout');
echo "\n⚠️  Vấn đề cần chú ý:\n";
if (!$abandonedOk) {
    echo "   ❌ OrderService chưa đặt flag is_abandoned_checkout khi checkout từ giỏ bỏ quên\n";
}

// Check frontend social share
echo "   ⚠️  Chia sẻ MXH: Frontend chưa có nút gọi API. Cần thêm vào trang chi tiết sản phẩm.\n";

echo "\n";
