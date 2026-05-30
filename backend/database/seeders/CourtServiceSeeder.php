<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Seeder: Dịch vụ bổ sung cho sân cầu lông
 */
class CourtServiceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $services = [
            [
                'service_name' => 'Nước suối',
                'service_code' => 'WATER',
                'unit'         => 'bottle',
                'unit_price'   => 10000,
                'description'  => 'Nước suối Aquafina 500ml',
                'is_active'    => true,
                'sort_order'   => 1,
            ],
            [
                'service_name' => 'Nước tăng lực',
                'service_code' => 'ENERGY',
                'unit'         => 'bottle',
                'unit_price'   => 15000,
                'description'  => 'Nước tăng lực Revive / Sting',
                'is_active'    => true,
                'sort_order'   => 2,
            ],
            [
                'service_name' => 'Cho thuê vợt',
                'service_code' => 'RACKET',
                'unit'         => 'set',
                'unit_price'   => 30000,
                'description'  => 'Vợt cầu lông Yonex / Lining cho thuê',
                'is_active'    => true,
                'sort_order'   => 3,
            ],
            [
                'service_name' => 'Cầu lông (1 quả)',
                'service_code' => 'SHUTTLE',
                'unit'         => 'piece',
                'unit_price'   => 8000,
                'description'  => 'Quả cầu lông thi đấu (1 quả)',
                'is_active'    => true,
                'sort_order'   => 4,
            ],
            [
                'service_name' => 'Cầu lông (hộp 12 quả)',
                'service_code' => 'SHUTTLE-BOX',
                'unit'         => 'set',
                'unit_price'   => 85000,
                'description'  => 'Hộp cầu lông 12 quả, phù hợp thi đấu',
                'is_active'    => true,
                'sort_order'   => 5,
            ],
            [
                'service_name' => 'Khăn lạnh',
                'service_code' => 'TOWEL',
                'unit'         => 'piece',
                'unit_price'   => 5000,
                'description'  => 'Khăn mát lạnh dùng 1 lần',
                'is_active'    => true,
                'sort_order'   => 6,
            ],
            [
                'service_name' => 'Cho thuê giày',
                'service_code' => 'SHOES',
                'unit'         => 'set',
                'unit_price'   => 25000,
                'description'  => 'Giày cầu lông chuyên dụng cho thuê',
                'is_active'    => true,
                'sort_order'   => 7,
            ],
            [
                'service_name' => 'Huấn luyện viên (1 giờ)',
                'service_code' => 'COACH',
                'unit'         => 'hour',
                'unit_price'   => 200000,
                'description'  => 'Dịch vụ huấn luyện viên kèm riêng 1 giờ',
                'is_active'    => false, // Tạm ngưng
                'sort_order'   => 8,
            ],
        ];

        foreach ($services as $service) {
            $exists = DB::table('court_services')
                ->where('service_code', $service['service_code'])
                ->exists();

            if (!$exists) {
                DB::table('court_services')->insert(array_merge($service, [
                    'image_url'  => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                echo "✅ Dịch vụ: {$service['service_name']} ({$service['service_code']})\n";
            } else {
                echo "ℹ️  {$service['service_code']} đã tồn tại, bỏ qua.\n";
            }
        }
    }
}
