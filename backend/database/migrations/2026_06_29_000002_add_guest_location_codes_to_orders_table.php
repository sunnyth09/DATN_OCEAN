<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'province_code')) {
                $table->integer('province_code')->nullable()->after('shipping_address');
            }
            if (! Schema::hasColumn('orders', 'district_code')) {
                $table->integer('district_code')->nullable()->after('province_code');
            }
            if (! Schema::hasColumn('orders', 'ward_code')) {
                $table->string('ward_code', 20)->nullable()->after('district_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['ward_code', 'district_code', 'province_code'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
