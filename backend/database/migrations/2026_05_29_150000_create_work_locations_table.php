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
        Schema::create('work_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // "Văn phòng chính", "Chi nhánh Nha Trang"
            $table->string('address')->nullable();                     // Địa chỉ text
            $table->decimal('latitude', 10, 8);                        // Vĩ độ
            $table->decimal('longitude', 11, 8);                       // Kinh độ
            $table->unsignedInteger('radius_meters')->default(200);    // Bán kính cho phép (mét)
            $table->boolean('is_active')->default(true);               // Có đang hoạt động không
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_locations');
    }
};
