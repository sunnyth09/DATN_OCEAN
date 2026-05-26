<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 20)->unique()->nullable()->after('status');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            $table->timestamp('affiliate_registered_at')->nullable()->after('referred_by');
            $table->boolean('is_affiliate')->default(false)->after('affiliate_registered_at');

            $table->foreign('referred_by')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by', 'affiliate_registered_at', 'is_affiliate']);
        });
    }
};
