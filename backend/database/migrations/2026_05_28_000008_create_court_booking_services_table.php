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
        Schema::create('court_booking_services', function (Blueprint $table) {
            $table->id('booking_service_id');
            $table->unsignedBigInteger('booking_id');
            $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('service_id')->on('court_services')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->integer('unit_price');                // Snapshot giá tại thời điểm thêm
            $table->integer('subtotal');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->foreign('added_by')->references('admin_id')->on('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_booking_services');
    }
};
