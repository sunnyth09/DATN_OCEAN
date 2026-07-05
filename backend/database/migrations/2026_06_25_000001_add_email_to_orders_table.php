<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Thêm cột email vào bảng orders
 *
 * Mục đích: Lưu email người nhận đơn hàng để gửi email xác nhận.
 * - Khách vãng lai (guest): không có tài khoản → bắt buộc lấy email từ form checkout.
 * - Khách đăng nhập: mặc định dùng email tài khoản, nhưng vẫn cho phép nhập email khác.
 *
 * Cron `app:send-order-emails` sẽ ưu tiên gửi tới orders.email,
 * fallback về users.email nếu cột này rỗng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'email')) {
                $table->string('email', 255)->nullable()->after('recipient_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
