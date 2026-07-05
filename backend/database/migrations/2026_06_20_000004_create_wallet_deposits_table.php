<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('deposit_code', 30)->unique(); // WDP-xxxxx (idempotency key)
            $table->decimal('amount', 15, 2);
            $table->string('method', 20); // vnpay, momo, bank_transfer
            $table->enum('status', ['pending', 'completed', 'failed', 'expired'])->default('pending');
            $table->string('gateway_transaction_id', 100)->nullable(); // Transaction ID từ gateway
            $table->json('gateway_response')->nullable(); // Response payload từ gateway
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('user_id')->on('users')
                  ->onDelete('cascade');

            $table->index(['user_id', 'status']);
            $table->index('deposit_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_deposits');
    }
};
