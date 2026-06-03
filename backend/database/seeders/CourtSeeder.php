<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Seeder: Tạo 7 sân cầu lông + lịch hoạt động + bảng giá
 */
class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // =============================================
        // 1. TẠO 7 SÂN CẦU LÔNG
        // =============================================
        $courts = [
            [
                'court_name'  => 'Sân 1',
                'court_code'  => 'SAN-01',
                'type'        => 'standard',
                'description' => 'Sân cầu lông tiêu chuẩn, mặt thảm PVC chống trơn trượt, ánh sáng đạt chuẩn thi đấu.',
                'surface'     => 'Thảm PVC',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 1,
            ],
            [
                'court_name'  => 'Sân 2',
                'court_code'  => 'SAN-02',
                'type'        => 'standard',
                'description' => 'Sân cầu lông tiêu chuẩn, phù hợp luyện tập hàng ngày và thi đấu phong trào.',
                'surface'     => 'Thảm PVC',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 2,
            ],
            [
                'court_name'  => 'Sân 3',
                'court_code'  => 'SAN-03',
                'type'        => 'vip',
                'description' => 'Sân VIP với hệ thống điều hoà, ghế nghỉ riêng, bề mặt sàn gỗ cao cấp.',
                'surface'     => 'Sàn gỗ',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 3,
            ],
            [
                'court_name'  => 'Sân 4',
                'court_code'  => 'SAN-04',
                'type'        => 'vip',
                'description' => 'Sân VIP chất lượng cao, sàn nhập khẩu, phù hợp thi đấu chuyên nghiệp.',
                'surface'     => 'Sàn gỗ',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 4,
            ],
            [
                'court_name'  => 'Sân 5',
                'court_code'  => 'SAN-05',
                'type'        => 'indoor',
                'description' => 'Sân trong nhà có mái che, chống nắng mưa, thông gió tốt.',
                'surface'     => 'Composite',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 5,
            ],
            [
                'court_name'  => 'Sân 6',
                'court_code'  => 'SAN-06',
                'type'        => 'outdoor',
                'description' => 'Sân ngoài trời thoáng mát, phù hợp chơi buổi sáng và chiều tối.',
                'surface'     => 'Bê tông phủ cao su',
                'max_players' => 4,
                'status'      => 'active',
                'image_url'   => null,
                'sort_order'  => 6,
            ],
            [
                'court_name'  => 'Sân 7',
                'court_code'  => 'SAN-07',
                'type'        => 'standard',
                'description' => 'Sân tiêu chuẩn đang tạm ngưng để bảo trì hệ thống đèn chiếu sáng.',
                'surface'     => 'Thảm PVC',
                'max_players' => 4,
                'status'      => 'maintenance',
                'image_url'   => null,
                'sort_order'  => 7,
            ],
        ];

        $courtIds = [];
        foreach ($courts as $court) {
            $existing = DB::table('courts')->where('court_code', $court['court_code'])->first();
            if ($existing) {
                $courtIds[] = $existing->court_id;
                echo "ℹ️  Sân {$court['court_code']} đã tồn tại, bỏ qua.\n";
            } else {
                $id = DB::table('courts')->insertGetId(array_merge($court, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $courtIds[] = $id;
                echo "✅ Tạo {$court['court_name']} ({$court['court_code']})\n";
            }
        }

        // =============================================
        // 2. TẠO LỊCH HOẠT ĐỘNG (court_schedules) — 7 ngày/tuần cho mỗi sân
        // =============================================
        // day_of_week: 0=Chủ Nhật, 1=Thứ Hai, ..., 6=Thứ Bảy
        echo "\n📅 Tạo lịch hoạt động...\n";
        foreach ($courtIds as $index => $courtId) {
            // Sân 7 đang maintenance → vẫn tạo schedule nhưng is_active = false
            $isActive = ($courts[$index]['status'] === 'active');

            for ($dow = 0; $dow <= 6; $dow++) {
                // Cuối tuần mở sớm hơn
                $openTime = ($dow == 0 || $dow == 6) ? '05:00:00' : '06:00:00';
                $closeTime = '22:00:00';

                $exists = DB::table('court_schedules')
                    ->where('court_id', $courtId)
                    ->where('day_of_week', $dow)
                    ->exists();

                if (!$exists) {
                    DB::table('court_schedules')->insert([
                        'court_id'    => $courtId,
                        'day_of_week' => $dow,
                        'open_time'   => $openTime,
                        'close_time'  => $closeTime,
                        'is_active'   => $isActive,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                }
            }
        }
        echo "✅ Lịch hoạt động cho 7 sân × 7 ngày đã tạo.\n";

        // =============================================
        // 3. TẠO BẢNG GIÁ (court_prices)
        // =============================================
        echo "\n💰 Tạo bảng giá...\n";

        // Giá mặc định theo loại sân
        $priceMap = [
            'standard' => 80000,
            'vip'      => 150000,
            'indoor'   => 100000,
            'outdoor'  => 70000,
        ];

        foreach ($courtIds as $index => $courtId) {
            $type = $courts[$index]['type'];
            $basePrice = $priceMap[$type];

            $priceConfigs = [
                // Ngày thường - Sáng sớm (giảm giá)
                ['price_name' => 'Ngày thường - Sáng sớm', 'day_type' => 'weekday', 'from_time' => '05:00:00', 'to_time' => '08:00:00', 'price' => (int)($basePrice * 0.8)],
                // Ngày thường - Giờ hành chính
                ['price_name' => 'Ngày thường - Giờ hành chính', 'day_type' => 'weekday', 'from_time' => '08:00:00', 'to_time' => '17:00:00', 'price' => $basePrice],
                // Ngày thường - Giờ cao điểm
                ['price_name' => 'Ngày thường - Giờ cao điểm', 'day_type' => 'weekday', 'from_time' => '17:00:00', 'to_time' => '22:00:00', 'price' => (int)($basePrice * 1.3)],
                // Cuối tuần - Cả ngày
                ['price_name' => 'Cuối tuần - Cả ngày', 'day_type' => 'weekend', 'from_time' => '05:00:00', 'to_time' => '22:00:00', 'price' => (int)($basePrice * 1.2)],
            ];

            foreach ($priceConfigs as $pc) {
                $exists = DB::table('court_prices')
                    ->where('court_id', $courtId)
                    ->where('day_type', $pc['day_type'])
                    ->where('from_time', $pc['from_time'])
                    ->where('to_time', $pc['to_time'])
                    ->exists();

                if (!$exists) {
                    DB::table('court_prices')->insert([
                        'court_id'       => $courtId,
                        'price_name'     => $pc['price_name'],
                        'day_type'       => $pc['day_type'],
                        'from_time'      => $pc['from_time'],
                        'to_time'        => $pc['to_time'],
                        'price_per_hour' => $pc['price'],
                        'is_active'      => true,
                        'effective_from' => null,
                        'effective_to'   => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                }
            }
        }
        echo "✅ Bảng giá cho 7 sân đã tạo.\n";
    }
}
