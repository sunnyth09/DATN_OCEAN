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
            if (!Schema::hasColumn('orders', 'processing_at')) {
                $table->timestamp('processing_at')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('orders', 'packing_at')) {
                $table->timestamp('packing_at')->nullable()->after('processing_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'processing_at')) {
                $table->dropColumn('processing_at');
            }
            if (Schema::hasColumn('orders', 'packing_at')) {
                $table->dropColumn('packing_at');
            }
        });
    }
};
