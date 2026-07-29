<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder: Lịch bảo trì demo cho sân cầu lông
 */
class CourtMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $today = Carbon::today();

        // Lấy admin đầu tiên
        $admin = DB::table('admins')->first();
        $adminId = $admin ? $admin->admin_id : null;

        // Lấy danh sách sân
        $courts = DB::table('courts')->whereNull('deleted_at')->get();

        if ($courts->isEmpty()) {
            echo "⚠️  Không tìm thấy sân. Chạy CourtSeeder trước!\n";

            return;
        }

        echo "\n🔧 Tạo lịch bảo trì demo...\n";

        $maintenances = [
            // Bảo trì đã hoàn thành (quá khứ)
            [
                'court_id' => $courts[0]->court_id,
                'title' => 'Thay thảm PVC sân 1',
                'description' => 'Thay toàn bộ thảm PVC đã cũ, lắp đặt thảm mới nhập khẩu từ Thái Lan.',
                'start_datetime' => $today->copy()->subDays(10)->setHour(8)->format('Y-m-d H:i:s'),
                'end_datetime' => $today->copy()->subDays(10)->setHour(12)->format('Y-m-d H:i:s'),
                'status' => 'completed',
            ],
            // Bảo trì đã hoàn thành
            [
                'court_id' => $courts[1]->court_id,
                'title' => 'Sửa chữa hệ thống đèn sân 2',
                'description' => 'Thay 4 bóng đèn LED bị hỏng, kiểm tra lại hệ thống điện.',
                'start_datetime' => $today->copy()->subDays(5)->setHour(6)->format('Y-m-d H:i:s'),
                'end_datetime' => $today->copy()->subDays(5)->setHour(10)->format('Y-m-d H:i:s'),
                'status' => 'completed',
            ],
            // Bảo trì đang diễn ra (Sân 7 - maintenance)
            [
                'court_id' => $courts[6]->court_id, // Sân 7
                'title' => 'Bảo trì tổng thể sân 7',
                'description' => 'Sơn lại vạch kẻ sân, kiểm tra lưới, thay thảm và hệ thống chiếu sáng.',
                'start_datetime' => $today->copy()->subDay()->setHour(6)->format('Y-m-d H:i:s'),
                'end_datetime' => $today->copy()->addDays(2)->setHour(22)->format('Y-m-d H:i:s'),
                'status' => 'in_progress',
            ],
            // Bảo trì đã lên lịch (tương lai)
            [
                'court_id' => $courts[2]->court_id,
                'title' => 'Kiểm tra định kỳ sàn gỗ sân 3',
                'description' => 'Kiểm tra, đánh bóng và bảo dưỡng sàn gỗ. Dự kiến mất 4 tiếng.',
                'start_datetime' => $today->copy()->addDays(5)->setHour(6)->format('Y-m-d H:i:s'),
                'end_datetime' => $today->copy()->addDays(5)->setHour(10)->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
            ],
            // Bảo trì đã hủy
            [
                'court_id' => $courts[4]->court_id,
                'title' => 'Sửa quạt thông gió sân 5 (Đã hủy)',
                'description' => 'Kế hoạch sửa quạt bị hủy do hàng về trễ.',
                'start_datetime' => $today->copy()->addDays(3)->setHour(8)->format('Y-m-d H:i:s'),
                'end_datetime' => $today->copy()->addDays(3)->setHour(12)->format('Y-m-d H:i:s'),
                'status' => 'cancelled',
            ],
        ];

        foreach ($maintenances as $m) {
            $exists = DB::table('court_maintenances')
                ->where('court_id', $m['court_id'])
                ->where('title', $m['title'])
                ->exists();

            if (! $exists) {
                DB::table('court_maintenances')->insert(array_merge($m, [
                    'created_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                echo "✅ Bảo trì: {$m['title']} ({$m['status']})\n";
            } else {
                echo "ℹ️  {$m['title']} đã tồn tại, bỏ qua.\n";
            }
        }
    }
}
