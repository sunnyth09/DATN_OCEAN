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
        Schema::create('open_play_waitlists', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('open_play_id');
            $table->foreign('open_play_id')->references('id')->on('open_plays')->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('position')->default(1);
            $table->enum('status', ['waiting', 'promoted', 'cancelled', 'expired'])->default('waiting');

            $table->dateTime('promoted_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->timestamps();

            // Unique constraint to prevent duplicate active waitlist entries for same user on same match
            $table->index(['open_play_id', 'status', 'position']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_play_waitlists');
    }
};
