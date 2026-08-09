<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('return_requests', 'return_shipping_method')) {
                $table->string('return_shipping_method', 30)->nullable()->after('refund_method');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_name')) {
                $table->string('return_pickup_name')->nullable()->after('return_shipping_method');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_phone')) {
                $table->string('return_pickup_phone', 30)->nullable()->after('return_pickup_name');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_address')) {
                $table->text('return_pickup_address')->nullable()->after('return_pickup_phone');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_province_code')) {
                $table->string('return_pickup_province_code', 30)->nullable()->after('return_pickup_address');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_district_code')) {
                $table->string('return_pickup_district_code', 30)->nullable()->after('return_pickup_province_code');
            }
            if (! Schema::hasColumn('return_requests', 'return_pickup_ward_code')) {
                $table->string('return_pickup_ward_code', 30)->nullable()->after('return_pickup_district_code');
            }
            if (! Schema::hasColumn('return_requests', 'videos')) {
                $table->json('videos')->nullable()->after('images');
            }
            if (! Schema::hasColumn('return_requests', 'return_ghn_order_code')) {
                $table->string('return_ghn_order_code', 100)->nullable()->after('return_carrier');
            }
            if (! Schema::hasColumn('return_requests', 'return_ghn_response')) {
                $table->json('return_ghn_response')->nullable()->after('return_ghn_order_code');
            }
            if (! Schema::hasColumn('return_requests', 'return_label_created_at')) {
                $table->timestamp('return_label_created_at')->nullable()->after('return_ghn_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $columns = [
                'return_label_created_at',
                'return_ghn_response',
                'return_ghn_order_code',
                'videos',
                'return_pickup_ward_code',
                'return_pickup_district_code',
                'return_pickup_province_code',
                'return_pickup_address',
                'return_pickup_phone',
                'return_pickup_name',
                'return_shipping_method',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('return_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
