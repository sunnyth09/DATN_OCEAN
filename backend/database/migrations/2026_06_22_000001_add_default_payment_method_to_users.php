<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('default_payment_method', 20)
                ->nullable()
                ->default(null)
                ->after('phone')
                ->comment('Preferred payment method for quick order: cod, bank_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_payment_method');
        });
    }
};
