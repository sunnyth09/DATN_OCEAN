<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng bảng coupons để hỗ trợ Coupon tự động (auto-apply) cho Combo:
 * - type 'combo': coupon áp tự động khi cart có đủ sản phẩm chỉ định (không cần nhập code)
 * - auto_apply: không cần user nhập code — hệ thống tự detect và apply
 *
 * Bảng coupon_products (NEW): liên kết coupon loại 'combo' với sản phẩm bắt buộc
 */
return new class extends Migration
{
    public function up(): void
    {
        // Thêm type 'combo' và flag auto_apply vào bảng coupons
        Schema::table('coupons', function (Blueprint $table) {
            // Đổi enum để thêm 'combo'
            // MySQL không cho ALTER ENUM trực tiếp, dùng CHANGE column
            \Illuminate\Support\Facades\DB::statement(
                "ALTER TABLE coupons MODIFY COLUMN type ENUM('fixed','percent','free_ship','combo') NOT NULL DEFAULT 'fixed'"
            );

            // Tự động áp dụng (không cần nhập code)
            $table->boolean('auto_apply')->default(false)->after('is_active');

            // Số lượng sản phẩm tối thiểu trong cart để trigger (cho type=combo)
            $table->unsignedInteger('min_product_qty')->default(1)->after('auto_apply');
        });

        // Bảng liên kết: coupon ↔ sản phẩm bắt buộc phải có trong cart
        // Dùng cho coupon type='combo' (auto-apply khi đủ sản phẩm)
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('product_id');
            // Số lượng tối thiểu của sản phẩm này (mặc định 1)
            $table->unsignedInteger('min_qty')->default(1);
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
            $table->unique(['coupon_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_products');

        Schema::table('coupons', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement(
                "ALTER TABLE coupons MODIFY COLUMN type ENUM('fixed','percent','free_ship') NOT NULL DEFAULT 'fixed'"
            );
            $table->dropColumn(['auto_apply', 'min_product_qty']);
        });
    }
};
