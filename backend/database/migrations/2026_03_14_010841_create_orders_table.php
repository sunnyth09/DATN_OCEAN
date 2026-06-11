<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->string('order_code', 30)->unique();
            $table->enum('order_type', ['online', 'pos'])->default('online');
            $table->foreignId('user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses', 'address_id')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions', 'promotion_id')->nullOnDelete();
            $table->string('recipient_name', 120);
            $table->string('recipient_phone', 20);
            $table->text('shipping_address');
            $table->text('note')->nullable();

            $table->enum('payment_method', ['cod', 'vnpay', 'momo', 'bank_transfer'])->default('cod');
            $table->enum('payment_status', ['unpaid', 'paid', 'failed', 'refunded', 'partially_refunded'])->default('unpaid');
            $table->enum('fulfillment_status', ['pending', 'confirmed', 'packing', 'shipping', 'delivered', 'completed', 'cancelled', 'returned'])->default('pending');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};