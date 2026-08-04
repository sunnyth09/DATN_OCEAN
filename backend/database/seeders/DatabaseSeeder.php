<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now()->toDateTimeString();

        // Kiểm tra super admin đã tồn tại chưa
        $exists = DB::select('SELECT * FROM users WHERE email = ?', ['admin123@gmail.com']);

        if (count($exists) === 0) {
            DB::insert(
                'INSERT INTO users (full_name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                ['Super Admin', 'admin123@gmail.com', Hash::make('123456'), 'admin', 'active', $now, $now]
            );

            echo "✅ Super Admin created: admin123@gmail.com / 123456\n";
        } else {
            echo "ℹ️ Super Admin already exists, skipping.\n";
        }

        // Kiểm tra user bình thường đã tồn tại chưa
        $userExists = DB::select('SELECT * FROM users WHERE email = ?', ['user123@gmail.com']);

        if (count($userExists) === 0) {
            DB::insert(
                'INSERT INTO users (full_name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                ['Normal User', 'user123@gmail.com', Hash::make('123456'), 'customer', 'active', $now, $now]
            );

            echo "✅ Normal User created: user123@gmail.com / 123456\n";
        } else {
            echo "ℹ️ Normal User already exists, skipping.\n";
        }

        // Gọi thêm CouponSeeder
        $this->call([
            CouponSeeder::class,
        ]);

        // Gọi seeder catalog thể thao hiện tại
        $this->call([
            CurrentSportsCatalogSeeder::class,
        ]);

        // Gọi seeder bài viết mẫu
        $this->call([
            PostSeeder::class,
        ]);

        // ========== COURT BOOKING SEEDERS ==========
        $this->call([
            CourtSeeder::class,
            CourtServiceSeeder::class,
            CourtMaintenanceSeeder::class,
            CourtBookingSeeder::class,
        ]);
    }
}
