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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'fulfillment_status'], 'idx_orders_user_fulfillment');
            $table->index(['fulfillment_status', 'created_at'], 'idx_orders_fulfillment_created');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['variant_id'], 'idx_order_items_variant');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index(['cart_id', 'variant_id'], 'idx_cart_items_cart_variant');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'status'], 'idx_product_variants_product_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_user_fulfillment');
            $table->dropIndex('idx_orders_fulfillment_created');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_variant');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('idx_cart_items_cart_variant');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_product_variants_product_status');
        });
    }
};
