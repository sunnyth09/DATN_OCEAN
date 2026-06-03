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
        Schema::dropIfExists('court_booking_locks');
        Schema::create('court_booking_locks', function (Blueprint $table) {
            $table->id('lock_id');
            $table->unsignedBigInteger('court_id');
            $table->foreign('court_id')->references('court_id')->on('courts')->cascadeOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->string('lock_token', 64)->unique();           // UUID để release
            $table->timestamp('expires_at');                      // Hết hạn sau 10 phút
            $table->timestamps();

            $table->index(['court_id', 'booking_date', 'start_time', 'end_time', 'expires_at'], 'court_booking_locks_lookup_idx');
            $table->index('expires_at');                          // Cho job cleanup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_booking_locks');
    }
};
