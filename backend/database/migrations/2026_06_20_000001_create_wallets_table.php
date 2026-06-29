<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id('wallet_id');
            $table->unsignedBigInteger('user_id')->unique();

            // 2 loại số dư tách biệt
            $table->decimal('deposit_balance', 15, 2)->default(0);    // Tiền nạp + refund + loyalty
            $table->decimal('commission_balance', 15, 2)->default(0); // Hoa hồng affiliate

            $table->decimal('frozen_balance', 15, 2)->default(0);     // Đang hold (pending booking/order)

            // Thống kê tích lũy
            $table->decimal('total_deposited', 15, 2)->default(0);    // Tổng đã nạp (deposit)
            $table->decimal('total_commission', 15, 2)->default(0);   // Tổng hoa hồng nhận
            $table->decimal('total_used', 15, 2)->default(0);         // Tổng đã dùng giảm giá

            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->string('pin_hash')->nullable();                    // PIN bảo mật (Phase 4)

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('user_id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
