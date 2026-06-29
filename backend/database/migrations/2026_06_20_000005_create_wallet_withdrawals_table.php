<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('withdrawal_code', 30)->unique();
            $table->decimal('amount', 15, 2);          // Số tiền rút (chưa tính phí)
            $table->decimal('fee', 15, 2)->default(0);  // Phí rút (1,000₫)
            $table->decimal('total_deducted', 15, 2);   // amount + fee (thực trừ từ ví)
            $table->string('bank_name', 100);
            $table->string('bank_account_name', 255);
            $table->string('bank_account_number', 50);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('user_id')->on('users')
                  ->onDelete('cascade');

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
    }
};
