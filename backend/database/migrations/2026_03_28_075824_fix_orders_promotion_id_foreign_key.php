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
            // Drop old foreign key referencing the promotions table
            $table->dropForeign(['promotion_id']);
            
            // Add new foreign key referencing the coupons table (id column)
            $table->foreign('promotion_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['promotion_id']);
            
            // Restore the old foreign key referencing promotions table (promotion_id column)
            $table->foreign('promotion_id')->references('promotion_id')->on('promotions')->nullOnDelete();
        });
    }
};
