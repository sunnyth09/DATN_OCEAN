<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('total_spent', 15, 2)->default(0)->after('status')->comment('Tổng chi tiêu tích lũy của khách hàng');
            $table->unsignedBigInteger('tier_id')->nullable()->after('total_spent')->comment('Hạng thành viên hiện tại');

            $table->foreign('tier_id')->references('id')->on('customer_tiers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tier_id']);
            $table->dropColumn(['tier_id', 'total_spent']);
        });
    }
};
