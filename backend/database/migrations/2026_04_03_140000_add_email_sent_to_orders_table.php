<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Thêm cột email_sent vào bảng orders
 *
 * Mục đích: Đánh dấu đơn hàng đã được gửi email xác nhận chưa.
 * Cron job sẽ quét các đơn chưa gửi mail (email_sent = false)
 * và gửi mail sau 5 phút kể từ khi đặt hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('email_sent')->default(false)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('email_sent');
        });
    }
};
