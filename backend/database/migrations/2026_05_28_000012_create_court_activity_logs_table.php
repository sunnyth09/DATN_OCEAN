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
        Schema::create('court_activity_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('actor_type', 30);                    // 'admin', 'user', 'system'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);                       // VD: 'booking.confirm'
            $table->string('subject_type', 60)->nullable();      // VD: 'CourtBooking'
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_activity_logs');
    }
};
