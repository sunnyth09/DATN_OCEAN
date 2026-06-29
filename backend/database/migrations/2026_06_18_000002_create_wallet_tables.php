<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create wallets table
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->decimal('affiliate_earnings', 15, 2)->default(0.00);
            $table->decimal('withdrawn_amount', 15, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });

        // 2. Create wallet_transactions table
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('amount', 15, 2);
            $table->string('type', 30); // deposit, withdraw, spend, refund, commission
            $table->string('status', 30)->default('completed'); // pending, completed, failed, cancelled
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 150)->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')
                ->references('id')
                ->on('wallets')
                ->onDelete('cascade');

            $table->index('wallet_id');
            $table->index('type');
            $table->index('status');
        });

        // 3. Alter affiliate_withdrawals to add withdrawal_method
        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            if (!Schema::hasColumn('affiliate_withdrawals', 'withdrawal_method')) {
                $table->string('withdrawal_method', 30)->default('bank')->after('amount');
            }
        });

        // 4. Alter orders to add is_abandoned_checkout and wallet_spent
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_abandoned_checkout')) {
                $table->boolean('is_abandoned_checkout')->default(false)->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'wallet_spent')) {
                $table->decimal('wallet_spent', 15, 2)->default(0.00)->after('grand_total');
            }
        });

        // 5. Alter carts to add is_abandoned_reminded
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'is_abandoned_reminded')) {
                $table->boolean('is_abandoned_reminded')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['is_abandoned_reminded']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_abandoned_checkout', 'wallet_spent']);
        });

        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['withdrawal_method']);
        });

        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
