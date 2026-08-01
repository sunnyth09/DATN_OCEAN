<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng wallets / wallet_transactions được tạo ở bộ migration 2026_06_20_*
        // (schema canonical: deposit_balance / commission_balance / direction...).
        // Migration này chỉ giữ các thay đổi cột đi kèm tính năng ví.

        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_withdrawals', 'withdrawal_method')) {
                $table->string('withdrawal_method', 30)->default('bank')->after('amount');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'is_abandoned_checkout')) {
                $table->boolean('is_abandoned_checkout')->default(false)->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'wallet_spent')) {
                $table->decimal('wallet_spent', 15, 2)->default(0.00)->after('grand_total');
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'is_abandoned_reminded')) {
                $table->boolean('is_abandoned_reminded')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'is_abandoned_reminded')) {
                $table->dropColumn(['is_abandoned_reminded']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_abandoned_checkout')) {
                $table->dropColumn(['is_abandoned_checkout']);
            }
            if (Schema::hasColumn('orders', 'wallet_spent')) {
                $table->dropColumn(['wallet_spent']);
            }
        });

        Schema::table('affiliate_withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_withdrawals', 'withdrawal_method')) {
                $table->dropColumn(['withdrawal_method']);
            }
        });
    }
};
