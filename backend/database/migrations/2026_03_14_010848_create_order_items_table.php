<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->foreignId('order_id')->constrained('orders', 'order_id')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products', 'product_id')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants', 'variant_id')->nullOnDelete();

            $table->string('product_name', 200);
            $table->string('variant_name', 150)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('color', 60)->nullable();
            $table->string('size', 60)->nullable();

            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};