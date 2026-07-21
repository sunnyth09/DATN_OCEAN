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
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 15, 2)->default(0)->after('total_amount');
            $table->index(['start_time', 'end_time'], 'idx_booking_time');
            $table->index(['court_id', 'status'], 'idx_court_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_time');
            $table->dropIndex('idx_court_status');
            $table->dropColumn('deposit_amount');
        });
    }
};
