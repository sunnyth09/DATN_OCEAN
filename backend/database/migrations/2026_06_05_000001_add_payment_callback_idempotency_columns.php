<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('paid_at');
            $table->string('confirmed_source', 20)->nullable()->after('confirmed_at');
            $table->string('post_payment_key', 190)->nullable()->after('confirmed_source');
            $table->dateTime('post_payment_processed_at')->nullable()->after('post_payment_key');
            $table->string('post_payment_source', 20)->nullable()->after('post_payment_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_at',
                'confirmed_source',
                'post_payment_key',
                'post_payment_processed_at',
                'post_payment_source',
            ]);
        });
    }
};
