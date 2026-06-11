<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_code')->nullable()->after('province');
            $table->unsignedInteger('district_code')->nullable()->after('district');
            $table->unsignedInteger('ward_code')->nullable()->after('ward');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'district_code', 'ward_code']);
        });
    }
};
