<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id('address_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->string('address_line', 255);
            $table->string('ward', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 120)->default('Vietnam');
            $table->boolean('is_default')->default(false);
            $table->enum('address_type', ['home', 'office', 'other'])->default('home');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};