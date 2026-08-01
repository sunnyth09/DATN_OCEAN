<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bank_name', 100);           // VD: MB Bank, Vietcombank
            $table->string('bank_short_name', 50)->nullable(); // VD: MB, VCB
            $table->string('bank_bin', 10)->nullable();  // Mã BIN ngân hàng (cho VietQR)
            $table->string('account_name', 255);         // Tên chủ TK
            $table->string('account_number', 50);        // Số tài khoản
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
            // Mỗi user không trùng số TK cùng ngân hàng
            $table->unique(['user_id', 'bank_bin', 'account_number'], 'unique_bank_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_accounts');
    }
};
