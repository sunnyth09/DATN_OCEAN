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
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sku')) {
                $table->string('sku', 100)->nullable()->after('brand_id')->comment('Mã sản phẩm chung');
            }
            if (! Schema::hasColumn('products', 'material')) {
                $table->string('material', 150)->nullable()->after('short_description')->comment('Chất liệu');
            }
            if (! Schema::hasColumn('products', 'origin')) {
                $table->string('origin', 150)->nullable()->after('material')->comment('Xuất xứ');
            }
            if (! Schema::hasColumn('products', 'style')) {
                $table->string('style', 150)->nullable()->after('origin')->comment('Kiểu dáng');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'material', 'origin', 'style']);
        });
    }
};
