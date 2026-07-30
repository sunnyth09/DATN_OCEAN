<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallets')) {
            return;
        }

        if (Schema::hasTable('wallet_transactions') && DB::getDriverName() !== 'sqlite') {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                try {
                    $table->dropForeign('wallet_transactions_wallet_id_foreign');
                } catch (Throwable) {
                    // FK may already be absent on some environments.
                }
            });
        }

        if (Schema::hasColumn('wallets', 'id') && !Schema::hasColumn('wallets', 'wallet_id') && DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE wallets CHANGE id wallet_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'deposit_balance')) {
                $table->decimal('deposit_balance', 15, 2)->default(0)->after('user_id');
            }
            if (!Schema::hasColumn('wallets', 'commission_balance')) {
                $table->decimal('commission_balance', 15, 2)->default(0)->after('deposit_balance');
            }
            if (!Schema::hasColumn('wallets', 'frozen_balance')) {
                $table->decimal('frozen_balance', 15, 2)->default(0)->after('commission_balance');
            }
            if (!Schema::hasColumn('wallets', 'total_deposited')) {
                $table->decimal('total_deposited', 15, 2)->default(0)->after('frozen_balance');
            }
            if (!Schema::hasColumn('wallets', 'total_commission')) {
                $table->decimal('total_commission', 15, 2)->default(0)->after('total_deposited');
            }
            if (!Schema::hasColumn('wallets', 'total_used')) {
                $table->decimal('total_used', 15, 2)->default(0)->after('total_commission');
            }
            if (!Schema::hasColumn('wallets', 'status')) {
                $table->enum('status', ['active', 'frozen', 'closed'])->default('active')->after('total_used');
            }
            if (!Schema::hasColumn('wallets', 'pin_hash')) {
                $table->string('pin_hash')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('wallets', 'balance')) {
            DB::statement('UPDATE wallets SET deposit_balance = balance WHERE deposit_balance = 0');
        }
        if (Schema::hasColumn('wallets', 'affiliate_earnings')) {
            DB::statement('UPDATE wallets SET commission_balance = affiliate_earnings WHERE commission_balance = 0');
        }

        if (!Schema::hasTable('wallet_transactions')) {
            return;
        }

        if (Schema::hasColumn('wallet_transactions', 'id') && !Schema::hasColumn('wallet_transactions', 'transaction_id')) {
            DB::statement('ALTER TABLE wallet_transactions CHANGE id transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'transaction_code')) {
                $table->string('transaction_code', 40)->nullable()->unique()->after('wallet_id');
            }
            if (!Schema::hasColumn('wallet_transactions', 'balance_type')) {
                $table->string('balance_type', 30)->default('deposit')->after('type');
            }
            if (!Schema::hasColumn('wallet_transactions', 'direction')) {
                $table->string('direction', 20)->default('credit')->after('balance_type');
            }
            if (!Schema::hasColumn('wallet_transactions', 'balance_before')) {
                $table->decimal('balance_before', 15, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('wallet_transactions', 'balance_after')) {
                $table->decimal('balance_after', 15, 2)->default(0)->after('balance_before');
            }
            if (!Schema::hasColumn('wallet_transactions', 'metadata')) {
                $table->json('metadata')->nullable()->after('status');
            }
        });

        DB::statement("UPDATE wallet_transactions SET transaction_code = CONCAT('WTX-MIG-', transaction_id) WHERE transaction_code IS NULL");

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('wallet_id')
                ->references('wallet_id')
                ->on('wallets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally left non-destructive. This migration upgrades legacy wallet data
        // to the canonical schema used by the application and should not drop money data.
    }
};
