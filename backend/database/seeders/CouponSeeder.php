<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Tạo một số coupon có sẵn theo Business Logic bài toán (fixed/percent/freeship)
        Coupon::insert([
            // Mã WELCOME (Dành để tặng tự động khi User đăng ký)
            [
                'code' => 'WELCOME2026',
                'type' => 'percent',
                'value' => 10.00,
                'max_discount_value' => 50000.00, // Tối đa 50k
                'min_order_value' => null, // Không yc đơn tối thiểu
                'usage_limit' => null, // Không giới hạn tổng
                'used_count' => 0,
                'user_usage_limit' => 1, // Mỗi người chỉ được dùng 1 lần
                'is_public' => false, // Mã này gửi nội bộ qua email, KHÔNG public trên Săn Voucher
                'is_first_order' => false,
                'start_date' => now(),
                'end_date' => null, // Mã vô thời hạn
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mã FIRST ORDER (Chỉ áp dụng đơn đầu tiên)
            [
                'code' => 'FIRSTORDER',
                'type' => 'fixed',
                'value' => 50000.00,
                'max_discount_value' => null,
                'min_order_value' => 200000.00, // Đơn trên 200k
                'usage_limit' => 1000,
                'used_count' => 0,
                'user_usage_limit' => 1,
                'is_public' => true, // Công khai kích cầu mua sắm
                'is_first_order' => true, // <-- QUAN TRỌNG: Flag đơn đầu
                'start_date' => now(),
                'end_date' => now()->addDays(90),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mã FREESHIP
            [
                'code' => 'FREESHIP50K',
                'type' => 'free_ship',
                'value' => 50000.00, // Hỗ trợ tối đa 50k ship
                'max_discount_value' => null,
                'min_order_value' => 150000.00,
                'usage_limit' => 500,
                'used_count' => 10,
                'user_usage_limit' => 3, // Được xài freeship 3 lần
                'is_public' => true,
                'is_first_order' => false,
                'start_date' => now()->subDays(2),
                'end_date' => now()->addDays(15),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Mã KHỦNG Flash Sale (Công khai nhanh)
            [
                'code' => 'FLASHSALE50',
                'type' => 'percent',
                'value' => 50.00,
                'max_discount_value' => 200000.00,
                'min_order_value' => 500000.00,
                'usage_limit' => 20, // CHỈ 20 LƯỢT
                'used_count' => 19, // Sắp hết
                'user_usage_limit' => 1,
                'is_public' => true,
                'is_first_order' => false,
                'start_date' => now()->subDays(1),
                'end_date' => now()->addDays(2), // Sắp hết hạn
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 2. Tạo thêm 10 coupons ngẫu nhiên bằng Factory
        Coupon::factory(16)->create();
    }
}
