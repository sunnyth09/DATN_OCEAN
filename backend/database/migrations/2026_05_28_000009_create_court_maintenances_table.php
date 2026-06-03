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
        Schema::dropIfExists('court_maintenances');
        Schema::create('court_maintenances', function (Blueprint $table) {
            $table->id('maintenance_id');
            $table->unsignedBigInteger('court_id');
            $table->foreign('court_id')->references('court_id')->on('courts')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('admin_id')->on('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['court_id', 'start_datetime', 'end_datetime', 'status'], 'court_maintenances_search_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_maintenances');
    }
};
