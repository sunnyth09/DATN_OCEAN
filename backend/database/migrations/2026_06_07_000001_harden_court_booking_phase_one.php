<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('court_bookings', 'customer_name')) {
                $table->string('customer_name', 120)->nullable()->after('staff_id');
            }

            if (! Schema::hasColumn('court_bookings', 'customer_phone')) {
                $table->string('customer_phone', 30)->nullable()->after('customer_name');
                $table->index('customer_phone');
            }

            if (! Schema::hasColumn('court_bookings', 'customer_email')) {
                $table->string('customer_email', 120)->nullable()->after('customer_phone');
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE court_bookings
                MODIFY status ENUM(
                    'pending',
                    'confirmed',
                    'checked_in',
                    'playing',
                    'completed',
                    'cancelled',
                    'no_show',
                    'extended',
                    'expired'
                ) NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE court_bookings
                MODIFY status ENUM(
                    'pending',
                    'confirmed',
                    'checked_in',
                    'playing',
                    'completed',
                    'cancelled',
                    'no_show',
                    'extended'
                ) NOT NULL DEFAULT 'pending'
            ");
        }

        Schema::table('court_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('court_bookings', 'customer_phone')) {
                $table->dropIndex(['customer_phone']);
            }

            $table->dropColumn(array_filter([
                Schema::hasColumn('court_bookings', 'customer_name') ? 'customer_name' : null,
                Schema::hasColumn('court_bookings', 'customer_phone') ? 'customer_phone' : null,
                Schema::hasColumn('court_bookings', 'customer_email') ? 'customer_email' : null,
            ]));
        });
    }
};
