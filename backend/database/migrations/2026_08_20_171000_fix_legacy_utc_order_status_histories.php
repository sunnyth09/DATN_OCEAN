<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Fix order_status_histories where happened_at was stored as UTC without offset
        DB::statement("
            UPDATE order_status_histories
            SET happened_at = DATE_ADD(happened_at, INTERVAL 7 HOUR)
            WHERE (source LIKE '%ocean%' OR source LIKE '%oe%' OR note LIKE '%Ocean Express%')
              AND happened_at IS NOT NULL
              AND created_at IS NOT NULL
              AND happened_at < DATE_SUB(created_at, INTERVAL 3 HOUR)
        ");

        // 2. Fix orders table where delivered_at is recorded before shipped_at due to UTC string
        DB::statement("
            UPDATE orders
            SET delivered_at = DATE_ADD(delivered_at, INTERVAL 7 HOUR)
            WHERE delivered_at IS NOT NULL
              AND shipped_at IS NOT NULL
              AND delivered_at < shipped_at
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe data repair migration — no rollback required
    }
};
