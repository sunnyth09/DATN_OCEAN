<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE orders
            MODIFY fulfillment_status ENUM(
                'pending',
                'confirmed',
                'processing',
                'packing',
                'shipping',
                'delivered',
                'completed',
                'cancelled',
                'return_requested',
                'return_approved',
                'return_rejected',
                'returned',
                'refunded'
            ) NOT NULL DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM(
                'unpaid',
                'paid',
                'failed',
                'refund_pending',
                'refunded',
                'refund_failed',
                'partially_refunded'
            ) NOT NULL DEFAULT 'unpaid'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE orders
            MODIFY fulfillment_status ENUM(
                'pending',
                'confirmed',
                'packing',
                'shipping',
                'delivered',
                'completed',
                'cancelled',
                'returned'
            ) NOT NULL DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM(
                'unpaid',
                'paid',
                'failed',
                'refunded',
                'partially_refunded'
            ) NOT NULL DEFAULT 'unpaid'
        ");
    }
};
