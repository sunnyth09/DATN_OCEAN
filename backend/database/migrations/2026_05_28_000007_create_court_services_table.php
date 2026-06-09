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
        Schema::create('court_services', function (Blueprint $table) {
            $table->id('service_id');
            $table->string('service_name', 100);
            $table->string('service_code', 30)->unique();         // VD: "WATER", "RACKET"
            $table->enum('unit', ['piece', 'bottle', 'set', 'hour', 'other'])->default('piece');
            $table->integer('unit_price');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_services');
    }
};
