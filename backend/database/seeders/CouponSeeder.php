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
        $defaultCoupons = [
            [
                'code' => 'WELCOME2026',
                'type' => 'percent',
                'value' => 10.00,
                'max_discount_value' => 50000.00,
                'min_order_value' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'user_usage_limit' => 1,
                'is_public' => false,
                'is_first_order' => false,
                'start_date' => now(),
                'end_date' => null,
                'is_active' => true,
            ],
            [
                'code' => 'FIRSTORDER',
                'type' => 'fixed',
                'value' => 50000.00,
                'max_discount_value' => null,
                'min_order_value' => 200000.00,
                'usage_limit' => 1000,
                'used_count' => 0,
                'user_usage_limit' => 1,
                'is_public' => true,
                'is_first_order' => true,
                'start_date' => now(),
                'end_date' => now()->addDays(90),
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP50K',
                'type' => 'free_ship',
                'value' => 50000.00,
                'max_discount_value' => null,
                'min_order_value' => 150000.00,
                'usage_limit' => 500,
                'used_count' => 10,
                'user_usage_limit' => 3,
                'is_public' => true,
                'is_first_order' => false,
                'start_date' => now()->subDays(2),
                'end_date' => now()->addDays(15),
                'is_active' => true,
            ],
            [
                'code' => 'FLASHSALE50',
                'type' => 'percent',
                'value' => 50.00,
                'max_discount_value' => 200000.00,
                'min_order_value' => 500000.00,
                'usage_limit' => 20,
                'used_count' => 19,
                'user_usage_limit' => 1,
                'is_public' => true,
                'is_first_order' => false,
                'start_date' => now()->subDays(1),
                'end_date' => now()->addDays(2),
                'is_active' => true,
            ],
        ];

        foreach ($defaultCoupons as $couponData) {
            Coupon::updateOrCreate(['code' => $couponData['code']], $couponData);
        }

        if (Coupon::count() < 20) {
            Coupon::factory(16)->create();
        }
    }
}
