<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id('promotion_usage_id');
            $table->foreignId('promotion_id')->constrained('promotions', 'promotion_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamp('used_at')->useCurrent();

            $table->index(['promotion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};