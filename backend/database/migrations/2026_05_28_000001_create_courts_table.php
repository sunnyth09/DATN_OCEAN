<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id('court_id');
            $table->string('court_name', 100);
            $table->string('court_code', 20)->unique();              // VD: "COURT-01"
            $table->enum('type', ['standard', 'vip', 'outdoor', 'indoor'])->default('standard');
            $table->text('description')->nullable();
            $table->string('surface', 50)->nullable();               // VD: "Gỗ", "Composite"
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->enum('status', [
                'active',       // Đang hoạt động
                'inactive',     // Tạm ngưng
                'maintenance',  // Đang bảo trì
                'closed',       // Đóng cửa vĩnh viễn
            ])->default('active');
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
