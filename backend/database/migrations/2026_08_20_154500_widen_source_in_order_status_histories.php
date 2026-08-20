<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_status_histories') && Schema::hasColumn('order_status_histories', 'source')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->string('source', 50)->default('system')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_status_histories') && Schema::hasColumn('order_status_histories', 'source')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->string('source', 20)->default('system')->change();
            });
        }
    }
};
