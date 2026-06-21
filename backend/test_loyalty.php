<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Support\Str;

$loyaltyService = app(LoyaltyService::class);
echo "=== BẮT ĐẦU TEST ĐIỂM THƯỞNG ===\n";

$user = User::where('role', 'customer')->first() ?? User::first();
echo "User test: {$user->email}\n";

// Dọn dẹp điểm cũ
LoyaltyTransaction::where('user_id', $user->user_id)->delete();
$user->update(['reward_points' => 0]);
$user->refresh();

$product = Product::first();

$order = Order::create([
    'user_id' => $user->user_id,
    'order_code' => 'TEST-ORD-' . Str::random(5),
    'status' => 'completed',
    'payment_status' => 'paid',
    'grand_total' => 200000,
    'subtotal' => 200000,
    'shipping_fee' => 0,
    'shipping_address' => 'Địa chỉ test',
    'recipient_name' => 'Test User',
    'recipient_phone' => '0123456789'
]);

// Test 1: Mua hàng hoàn thành
echo "\n[1] Mua hàng thành công (Đơn hàng 200.000đ):\n";
$tx1 = $loyaltyService->earnFromOrder($user, clone $order);
if ($tx1) {
    echo "=> Nhận: {$tx1->points} điểm. Lý do: {$tx1->description}\n";
}

// Test 2: Hoàn tất đơn bỏ quên
echo "\n[2] Hoàn tất đơn bỏ quên:\n";
$tx2 = $loyaltyService->earnAbandonedCart($user, 9999);
if ($tx2) {
    echo "=> Nhận: {$tx2->points} điểm. Lý do: {$tx2->description}\n";
}

// Test 3: Giới thiệu bạn bè
echo "\n[3] Giới thiệu bạn bè:\n";
// $tx3 = $loyaltyService->earnFromReferral($user, clone $order);

// Test 4: Đánh giá sản phẩm
echo "\n[4] Đánh giá sản phẩm:\n";
$tx4 = $loyaltyService->earnFromReview($user, $product->product_id);
if ($tx4) {
    echo "=> Đánh giá text nhận: {$tx4->points} điểm. Lý do: {$tx4->description}\n";
}
$tx5 = $loyaltyService->earnFromReviewWithImage($user, $product->product_id);
if ($tx5) {
    echo "=> Đánh giá có hình nhận: {$tx5->points} điểm. Lý do: {$tx5->description}\n";
}

// Test 5: Sinh nhật
echo "\n[5] Sinh nhật:\n";
$tx6 = $loyaltyService->earnBirthday($user);
if ($tx6) {
    echo "=> Nhận: {$tx6->points} điểm. Lý do: {$tx6->description}\n";
}

// Test 6: Chia sẻ MXH
echo "\n[6] Chia sẻ MXH:\n";
$tx7 = $loyaltyService->earnFromSocialShare($user, $product->product_id);
if ($tx7) {
    echo "=> Nhận: {$tx7->points} điểm. Lý do: {$tx7->description}\n";
}

$user->refresh();
echo "\n=== TỔNG KẾT SAU KHI EARN ===\n";
echo "Số điểm hiện tại: {$user->reward_points} điểm\n";

// Test 7: Tiêu điểm
echo "\n[7] Tiêu điểm (Checkout burn):\n";
try {
    $pointsToBurn = 15; // Burn 15 points
    if ($user->reward_points >= $pointsToBurn) {
        $tx8 = $loyaltyService->burnPoints($user, $pointsToBurn, clone $order);
        echo "=> Sử dụng: {$tx8->points} điểm. Lý do: {$tx8->description}\n";
    }
} catch (\Exception $e) {
    echo "=> Lỗi: " . $e->getMessage() . "\n";
}

$user->refresh();
echo "\n=== TỔNG KẾT CUỐI CÙNG ===\n";
echo "Số dư cuối: {$user->reward_points} điểm\n";

$order->delete();
