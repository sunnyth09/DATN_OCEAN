<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('wallet_id');
            $table->string('transaction_code', 30)->unique(); // WTX-xxxx (idempotency key)

            $table->enum('type', [
                'deposit',          // Nạp tiền trực tiếp (VNPay, MoMo, Bank)
                'commission',       // Hoa hồng affiliate auto-deposit
                'refund',           // Hoàn tiền đơn hàng
                'loyalty_convert',  // Quy đổi loyalty points → VNĐ
                'promo_credit',     // Admin cộng tiền khuyến mãi
                'order_discount',   // Dùng ví giảm giá đơn hàng
                'booking_payment',  // Thanh toán đặt sân
                'adjustment',       // Admin điều chỉnh thủ công
                'withdrawal',       // Rút tiền
            ]);

            // Phân biệt nguồn tiền
            $table->enum('balance_type', ['deposit', 'commission']); // Tiền thuộc loại nào
            $table->enum('direction', ['credit', 'debit']);          // credit = vào, debit = ra

            $table->decimal('amount', 15, 2);                        // Số tiền giao dịch
            $table->decimal('balance_before', 15, 2);                // Số dư loại tương ứng TRƯỚC
            $table->decimal('balance_after', 15, 2);                 // Số dư loại tương ứng SAU

            // Reference polymorphic — truy vết nguồn gốc
            $table->string('reference_type', 191)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('description', 500)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->json('metadata')->nullable();                    // Context bổ sung

            $table->timestamps();

            $table->foreign('wallet_id')
                  ->references('wallet_id')->on('wallets')
                  ->onDelete('cascade');

            $table->index(['wallet_id', 'type'], 'wtx_wallet_type_idx');
            $table->index(['wallet_id', 'created_at'], 'wtx_wallet_date_idx');
            $table->index(['reference_type', 'reference_id'], 'wtx_reference_idx');
            $table->index(['wallet_id', 'balance_type'], 'wtx_wallet_balance_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
