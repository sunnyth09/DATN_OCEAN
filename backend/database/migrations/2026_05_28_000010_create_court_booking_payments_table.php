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
        Schema::create('court_booking_payments', function (Blueprint $table) {
            $table->id('court_payment_id');
            $table->unsignedBigInteger('booking_id');
            $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
            $table->enum('payment_type', [
                'deposit',      // Thanh toán cọc
                'full',         // Thanh toán toàn phần
                'additional',   // Phần còn lại / dịch vụ phát sinh
                'refund',       // Hoàn tiền
            ]);
            $table->enum('payment_method', [
                'cash', 'vnpay', 'momo', 'bank_transfer', 'pos_card', 'pos_transfer',
            ]);
            $table->string('transaction_code', 120)->nullable();
            $table->integer('amount');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->foreign('processed_by')->references('admin_id')->on('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'payment_type', 'status']);
            $table->index('transaction_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_booking_payments');
    }
};
