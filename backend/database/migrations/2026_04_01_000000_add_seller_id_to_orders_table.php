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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'seller_id')) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('user_id')->comment('Account created or handled the POS order');
                
                // Cần đảm bảo bảng users đang tham chiếu ở đây có user_id
                // $table->foreign('seller_id')->references('user_id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'seller_id')) {
                // $table->dropForeign(['seller_id']);
                $table->dropColumn('seller_id');
            }
        });
    }
};
