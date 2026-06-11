<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm unique index cho order_code trên bảng orders.
     * 
     * Lý do: VNPay dùng order_code làm vnp_TxnRef — nếu trùng sẽ conflict giao dịch.
     * Unique index đảm bảo DB level constraint, không chỉ dựa vào application logic.
     */
    public function up(): void
    {
        $indexExists = collect(
            \DB::select("SHOW INDEX FROM orders WHERE Key_name = 'orders_order_code_unique'")
        )->isNotEmpty();

        if (!$indexExists) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('order_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_code']);
        });
    }
};
