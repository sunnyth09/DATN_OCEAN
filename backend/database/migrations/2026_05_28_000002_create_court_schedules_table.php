<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('court_schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->unsignedBigInteger('court_id');
            $table->foreign('court_id')->references('court_id')->on('courts')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week');  // 0=CN, 1=T2, ..., 6=T7
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['court_id', 'day_of_week']);
            $table->index(['court_id', 'day_of_week', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_schedules');
    }
};
