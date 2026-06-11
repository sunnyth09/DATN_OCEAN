<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng bảng flash_sales để hỗ trợ chế độ Combo/Bundle:
 * - is_combo: khi true, chỉ áp campaign_price khi cart có ĐỦ TẤT CẢ sản phẩm trong campaign
 * - min_combo_qty: số lượng sản phẩm tối thiểu của TỪNG item để trigger combo
 *
 * Khi is_combo = false → Flash Sale thông thường (từng sản phẩm riêng lẻ)
 * Khi is_combo = true  → Bundle: cần có đủ bộ → giảm giá campaign_price trên các sp trong set
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_sales', function (Blueprint $table) {
            // Flag: campaign này là combo bundle hay flash sale thường
            $table->boolean('is_combo')->default(false)->after('status');

            // Tên hiển thị mô tả combo (VD: "Mua cả bộ tiết kiệm 30%")
            $table->string('combo_label', 200)->nullable()->after('is_combo');
        });

        Schema::table('flash_sale_items', function (Blueprint $table) {
            // Số lượng tối thiểu của item này trong cart để trigger combo
            // (mặc định 1 = chỉ cần có mặt trong cart)
            $table->unsignedInteger('min_qty')->default(1)->after('sold');
        });
    }

    public function down(): void
    {
        Schema::table('flash_sales', function (Blueprint $table) {
            $table->dropColumn(['is_combo', 'combo_label']);
        });

        Schema::table('flash_sale_items', function (Blueprint $table) {
            $table->dropColumn('min_qty');
        });
    }
};
