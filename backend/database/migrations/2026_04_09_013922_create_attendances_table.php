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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('ip_address')->nullable(); // Lưu IP để biết nhân viên dùng wifi cửa hàng
            $table->decimal('latitude', 10, 8)->nullable(); // Tọa độ GPS
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('image_path')->nullable(); // Đường dẫn ảnh selfie nếu cần
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
