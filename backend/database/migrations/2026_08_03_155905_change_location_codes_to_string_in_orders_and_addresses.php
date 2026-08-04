<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('province_code', 30)->nullable()->change();
            $table->string('district_code', 30)->nullable()->change();
            $table->string('ward_code', 30)->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('province_code', 30)->nullable()->change();
            $table->string('district_code', 30)->nullable()->change();
            $table->string('ward_code', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Phục hồi lại integer nếu cần thiết (lưu ý: có thể mất data nếu data hiện tại là string)
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_code')->nullable()->change();
            $table->unsignedInteger('district_code')->nullable()->change();
            $table->unsignedInteger('ward_code')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('province_code')->nullable()->change();
            $table->integer('district_code')->nullable()->change();
            $table->string('ward_code', 20)->nullable()->change();
        });
    }
};
