<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đổi gateway_response từ text sang json để:
     * 1. Hỗ trợ query JSON trực tiếp trên MySQL 5.7+
     * 2. Đồng bộ với Model cast 'gateway_response' => 'array'
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('gateway_response')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('gateway_response')->nullable()->change();
        });
    }
};
