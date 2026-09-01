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
        Schema::create('open_plays', function (Blueprint $table) {
            $table->id();
            $table->string('open_play_code', 32)->unique(); // VD: OP-20260901-A1B2

            // Reference to Booking & Host
            $table->unsignedBigInteger('booking_id');
            $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();

            $table->unsignedBigInteger('host_user_id');
            $table->foreign('host_user_id')->references('user_id')->on('users')->cascadeOnDelete();

            // Match metadata
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('sport_type', 50)->default('badminton');
            $table->enum('skill_level', ['beginner', 'intermediate', 'advanced', 'all_levels'])->default('all_levels');
            $table->enum('gender_rule', ['any', 'male_only', 'female_only', 'mixed'])->default('any');
            $table->enum('match_type', ['singles', 'doubles', 'practice', 'casual'])->default('doubles');

            // Capacity & Slots
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->unsignedTinyInteger('current_players')->default(1); // Host is automatically 1st player

            // Rules & Modes
            $table->enum('join_mode', ['auto', 'approval'])->default('auto');
            $table->enum('payment_mode', ['host_pays', 'split_payment'])->default('host_pays');
            $table->unsignedInteger('slot_price')->default(0);

            // Status
            $table->enum('status', ['open', 'full', 'ongoing', 'completed', 'cancelled'])->default('open');
            $table->text('rules')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['host_user_id', 'status']);
            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_plays');
    }
};
