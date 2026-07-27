<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['fixed', 'percent', 'free_ship']);

        // Tạo code đẹp kiểu SALE30K, GIAM15P, SHIP20K,...
        $prefixes = ['SALE', 'GIAM', 'DEAL', 'HOT', 'MEGA', 'SUPER', 'VIP', 'OCEAN', 'COOL', 'LUCKY', 'SAVE', 'BOOM', 'TOP', 'MAX', 'PRO'];
        $code = $this->faker->randomElement($prefixes).$this->faker->unique()->numerify('##').$this->faker->randomElement(['K', 'VN', '', 'X']);

        $value = match ($type) {
            'percent' => $this->faker->randomElement([5, 10, 15, 20, 25, 30, 40, 50]),
            'fixed' => $this->faker->randomElement([10000, 20000, 30000, 50000, 70000, 100000]),
            'free_ship' => $this->faker->randomElement([15000, 20000, 30000, 50000]),
        };

        return [
            'code' => strtoupper($code),
            'type' => $type,
            'value' => $value,
            'max_discount_value' => $type === 'percent'
                ? $this->faker->optional(0.6)->randomElement([30000, 50000, 100000, 200000, 500000])
                : null,
            'min_order_value' => $this->faker->optional(0.7)->randomElement([100000, 150000, 200000, 300000, 500000]),
            'usage_limit' => $this->faker->optional(0.5)->randomElement([20, 50, 100, 200, 500, 1000]),
            'used_count' => $this->faker->numberBetween(0, 30),
            'user_usage_limit' => $this->faker->randomElement([1, 1, 1, 2, 3, 5]),
            'is_public' => $this->faker->boolean(75),
            'is_first_order' => $this->faker->boolean(15),
            'start_date' => $this->faker->dateTimeBetween('-2 weeks', '+1 week'),
            'end_date' => $this->faker->dateTimeBetween('+1 month', '+4 months'),
            'is_active' => $this->faker->boolean(80),
        ];
    }
}
