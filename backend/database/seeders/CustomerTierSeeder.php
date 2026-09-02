<?php

namespace Database\Seeders;

use App\Models\CustomerTier;
use Illuminate\Database\Seeder;

class CustomerTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            [
                'name' => 'Đồng',
                'min_spent' => 0,
                'discount_percent' => 0,
                'color' => '#CD7F32', // Bronze
                'icon_url' => null,
            ],
            [
                'name' => 'Bạc',
                'min_spent' => 2000000,
                'discount_percent' => 2,
                'color' => '#C0C0C0', // Silver
                'icon_url' => null,
            ],
            [
                'name' => 'Vàng',
                'min_spent' => 5000000,
                'discount_percent' => 5,
                'color' => '#FFD700', // Gold
                'icon_url' => null,
            ],
            [
                'name' => 'Kim Cương',
                'min_spent' => 10000000,
                'discount_percent' => 10,
                'color' => '#b9f2ff', // Diamond
                'icon_url' => null,
            ],
        ];

        foreach ($tiers as $tier) {
            CustomerTier::updateOrCreate(
                ['name' => $tier['name']],
                $tier
            );
        }
    }
}
