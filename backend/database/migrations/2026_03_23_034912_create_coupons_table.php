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
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigIncrements('id'); 
            $table->string('code')->unique(); 
            $table->enum('type', ['fixed', 'percent', 'free_ship'])->default('fixed'); 
        
            $table->decimal('value', 10, 2); 
            $table->decimal('max_discount_value', 10, 2)->nullable(); 
            $table->decimal('min_order_value', 10, 2)->nullable(); 
            $table->integer('usage_limit')->nullable(); 
            $table->integer('used_count')->default(0); 
            $table->integer('user_usage_limit')->default(1); 
            $table->boolean('is_public')->default(true); 
            $table->boolean('is_first_order')->default(false); 
            $table->dateTime('start_date')->nullable(); 
            $table->dateTime('end_date')->nullable(); 
            $table->boolean('is_active')->default(true); 
            $table->softDeletes(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
