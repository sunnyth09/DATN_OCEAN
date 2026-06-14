<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                      // Tên khu vực
            $table->text('provinces')->nullable();                       // JSON array tỉnh/thành
            $table->unsignedInteger('shipping_fee')->default(0);         // Phí ship (VNĐ)
            $table->unsignedInteger('free_ship_threshold')->nullable();  // Miễn phí khi đơn >= X
            $table->string('delivery_time', 50)->nullable();             // "1-2 ngày"
            $table->unsignedInteger('priority')->default(50);            // Độ ưu tiên (cao = quan trọng hơn)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
