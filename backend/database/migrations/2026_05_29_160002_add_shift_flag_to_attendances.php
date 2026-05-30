<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('work_shift_id')->nullable()->after('work_location_id');
            $table->boolean('is_flagged')->default(false)->after('status');
            $table->string('flag_note', 500)->nullable()->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['work_shift_id', 'is_flagged', 'flag_note']);
        });
    }
};
