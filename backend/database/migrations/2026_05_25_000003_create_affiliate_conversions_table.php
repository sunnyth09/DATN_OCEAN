<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('order_id')->unique();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('commission_rate', 5, 2)->default(5.00);
            $table->decimal('commission_amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'cancelled', 'paid'])->default('pending');
            $table->timestamps();

            $table->foreign('referrer_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('buyer_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('order_id')
                ->references('order_id')
                ->on('orders')
                ->onDelete('cascade');

            $table->index('referrer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_conversions');
    }
};
