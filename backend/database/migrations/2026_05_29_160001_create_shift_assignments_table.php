<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');          // ID nhân viên
            $table->string('user_type', 10);                 // 'admin' hoặc 'user'
            $table->unsignedBigInteger('work_shift_id');     // Ca nào
            $table->tinyInteger('day_of_week');              // 0=CN, 1=T2, 2=T3, ..., 6=T7
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Mỗi nhân viên chỉ được phân 1 lần cho 1 ca trong 1 ngày
            $table->unique(
                ['user_id', 'user_type', 'work_shift_id', 'day_of_week'],
                'sa_unique'
            );

            $table->foreign('work_shift_id')
                  ->references('id')
                  ->on('work_shifts')
                  ->onDelete('cascade');

            $table->index(['user_id', 'user_type', 'day_of_week'], 'sa_user_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
