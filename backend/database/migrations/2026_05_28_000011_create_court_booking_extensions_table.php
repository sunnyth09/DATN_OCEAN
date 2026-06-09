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
        Schema::create('court_booking_extensions', function (Blueprint $table) {
            $table->id('extension_id');
            $table->unsignedBigInteger('booking_id');
            $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
            $table->time('original_end_time');
            $table->time('extended_end_time');
            $table->unsignedSmallInteger('extension_minutes');
            $table->integer('extra_amount');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('admin_id')->on('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_booking_extensions');
    }
};
