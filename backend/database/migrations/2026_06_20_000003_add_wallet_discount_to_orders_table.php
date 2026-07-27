<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'wallet_deposit_discount')) {
                $table->decimal('wallet_deposit_discount', 15, 2)
                    ->default(0)
                    ->after('discount_amount');
            }

            if (! Schema::hasColumn('orders', 'wallet_commission_discount')) {
                $table->decimal('wallet_commission_discount', 15, 2)
                    ->default(0)
                    ->after('wallet_deposit_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['wallet_deposit_discount', 'wallet_commission_discount']);
        });
    }
};
