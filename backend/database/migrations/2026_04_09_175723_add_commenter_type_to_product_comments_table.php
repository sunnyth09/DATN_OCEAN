<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_comments', function (Blueprint $table) {
            $table->string('commenter_type', 10)->default('user')->after('user_id')
                  ->comment('user = from users table, admin = from admins table');
        });
    }

    public function down(): void
    {
        Schema::table('product_comments', function (Blueprint $table) {
            $table->dropColumn('commenter_type');
        });
    }
};
