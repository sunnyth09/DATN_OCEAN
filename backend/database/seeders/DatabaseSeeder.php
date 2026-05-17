<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now()->toDateTimeString();

        // Kiểm tra super admin đã tồn tại chưa
        $exists = DB::select("SELECT * FROM users WHERE email = ?", ['admin123@gmail.com']);

        if (count($exists) === 0) {
            DB::insert(
                "INSERT INTO users (full_name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                ['Super Admin', 'admin123@gmail.com', Hash::make('123456'), 'admin', 'active', $now, $now]
            );

            echo "✅ Super Admin created: admin123@gmail.com / 123456\n";
        } else {
            echo "ℹ️ Super Admin already exists, skipping.\n";
        }

        // Gọi thêm CouponSeeder
        $this->call([
            CouponSeeder::class,
        ]);

        // Gọi CleanProductSeeder — Xóa sạch + tạo 100 SP thời trang
        $this->call([
            CleanProductSeeder::class,
        ]);
    }
}
