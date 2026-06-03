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
        Schema::create('court_prices', function (Blueprint $table) {
            $table->id('price_id');
            $table->unsignedBigInteger('court_id');
            $table->foreign('court_id')->references('court_id')->on('courts')->onDelete('cascade');
            $table->string('price_name', 100)->nullable();           // VD: "Giờ cao điểm"
            $table->enum('day_type', ['weekday', 'weekend', 'holiday', 'all'])->default('all');
            $table->time('from_time');
            $table->time('to_time');
            $table->decimal('price_per_hour', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['court_id', 'day_type', 'is_active']);
            $table->index(['from_time', 'to_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_prices');
    }
};
