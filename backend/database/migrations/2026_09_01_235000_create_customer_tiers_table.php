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
        Schema::create('customer_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('min_spent', 15, 2)->default(0); // Mốc chi tiêu (doanh số) tối thiểu để đạt
            $table->decimal('discount_percent', 5, 2)->default(0); // Giảm giá mặc định cho mọi đơn hàng (VD: 5.00)
            $table->string('icon_url')->nullable();
            $table->string('color', 20)->nullable(); // Hex color code
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_tiers');
    }
};
