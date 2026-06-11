<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild bảng loyalty_transactions với đầy đủ schema.
 * Migration cũ (2026_06_06_092816) chỉ có id + timestamps (rỗng).
 * Migration này thêm đầy đủ cột cần thiết.
 *
 * Schema:
 *   user_id          → user sở hữu giao dịch
 *   type             → earn | burn | expire | adjust | refund
 *   points           → số điểm (luôn dương; dấu được xác định bởi type)
 *   balance_before   → điểm trước giao dịch (audit)
 *   balance_after    → điểm sau giao dịch (audit)
 *   reference_type   → Model class name (Order, ReturnRequest, v.v.)
 *   reference_id     → ID của record liên quan
 *   description      → mô tả tự do
 *   expires_at       → khi nào điểm này hết hạn (nullable = không hết hạn)
 *   expired_at       → thực tế đã expire lúc nào (audit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id');
            $table->enum('type', [
                'earn',    // Mua hàng, giới thiệu, sinh nhật, ...
                'burn',    // Dùng điểm để giảm giá
                'expire',  // Điểm tự động hết hạn (job)
                'adjust',  // Admin điều chỉnh thủ công
                'refund',  // Hoàn điểm khi huỷ/trả đơn hàng
            ])->after('user_id');

            $table->unsignedInteger('points')->after('type');                     // Số điểm (luôn >= 0)
            $table->unsignedInteger('balance_before')->default(0)->after('points'); // Số dư trước
            $table->unsignedInteger('balance_after')->default(0)->after('balance_before');  // Số dư sau

            $table->string('reference_type', 100)->nullable()->after('balance_after');  // Model class
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type'); // Record ID

            $table->string('description', 300)->nullable()->after('reference_id');

            $table->timestamp('expires_at')->nullable()->after('description'); // Điểm earn này hết hạn khi nào
            $table->timestamp('expired_at')->nullable()->after('expires_at');  // Thực tế đã expire lúc nào

            // Index cho các query phổ biến
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['expires_at', 'type']);  // Cho expiry job

            // FK
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['expires_at', 'type']);
            $table->dropColumn([
                'user_id', 'type', 'points', 'balance_before', 'balance_after',
                'reference_type', 'reference_id', 'description', 'expires_at', 'expired_at',
            ]);
        });
    }
};
