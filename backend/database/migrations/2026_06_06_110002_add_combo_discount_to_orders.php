<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột combo_discount vào orders để lưu riêng discount từ combo/bundle.
 * Tách biệt với discount_amount (coupon) để audit rõ ràng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Discount từ Flash Sale combo + Auto-apply combo voucher
            $table->decimal('combo_discount', 15, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('combo_discount');
        });
    }
};
