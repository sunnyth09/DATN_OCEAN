<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Thêm cột date_of_birth và reward_points vào bảng users
 *
 * - date_of_birth: Lưu ngày sinh để kiểm tra sinh nhật hàng ngày
 * - reward_points: Lưu điểm thưởng tích lũy (tặng khi giỏ hàng bỏ quên...)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ngày sinh - nullable vì user cũ chưa có thông tin này
            $table->date('date_of_birth')->nullable()->after('avatar_url');

            // Điểm thưởng tích lũy - mặc định 0
            $table->unsignedInteger('reward_points')->default(0)->after('date_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'reward_points']);
        });
    }
};
