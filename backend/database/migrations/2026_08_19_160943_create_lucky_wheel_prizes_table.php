<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lucky_wheel_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên phần thưởng
            $table->string('type'); // points, voucher, empty
            $table->integer('value')->default(0); // Giá trị (vd: 100 điểm)
            $table->decimal('probability', 5, 2)->default(0); // Tỷ lệ trúng (%)
            $table->string('color')->nullable(); // Màu sắc lát cắt trên vòng quay
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert initial prizes
        DB::table('lucky_wheel_prizes')->insert([
            ['name' => '10 Xu', 'type' => 'points', 'value' => 10, 'probability' => 30.00, 'color' => '#FFC107', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chúc bạn MM lần sau', 'type' => 'empty', 'value' => 0, 'probability' => 40.00, 'color' => '#E0E0E0', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '50 Xu', 'type' => 'points', 'value' => 50, 'probability' => 15.00, 'color' => '#FF9800', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Voucher 10%', 'type' => 'voucher', 'value' => 10, 'probability' => 5.00, 'color' => '#4CAF50', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '100 Xu', 'type' => 'points', 'value' => 100, 'probability' => 8.00, 'color' => '#F44336', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Voucher 20%', 'type' => 'voucher', 'value' => 20, 'probability' => 2.00, 'color' => '#9C27B0', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lucky_wheel_prizes');
    }
};
