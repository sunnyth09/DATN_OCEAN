<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Thêm các POS payment methods vào ENUM
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo', 'bank_transfer', 'pos_cash', 'pos_transfer', 'pos_card') DEFAULT 'cod'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục lại ENUM gốc
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cod', 'vnpay', 'momo', 'bank_transfer') DEFAULT 'cod'");
    }
};
