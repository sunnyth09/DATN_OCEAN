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
        Schema::create('open_play_participants', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('open_play_id');
            $table->foreign('open_play_id')->references('id')->on('open_plays')->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();

            // Guest info if user not logged in initially
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_phone', 20)->nullable();

            $table->enum('role', ['host', 'participant'])->default('participant');

            // Participant lifecycle status
            $table->enum('status', [
                'registered',
                'pending',
                'approved',
                'rejected',
                'confirmed',
                'cancelled',
                'checked_in',
                'no_show',
                'completed',
            ])->default('confirmed');

            // Individual Payment Status
            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid',
                'refunded',
                'free',
            ])->default('free');

            $table->unsignedInteger('payment_amount')->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_transaction_code', 100)->nullable();

            // Timestamps for audit & lifecycle
            $table->dateTime('joined_at')->useCurrent();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('check_in_token', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['open_play_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_play_participants');
    }
};
