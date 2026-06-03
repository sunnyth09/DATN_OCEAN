<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Thêm cột date_of_birth vào bảng admins
 * Cho phép admin cập nhật ngày sinh trong trang Profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
