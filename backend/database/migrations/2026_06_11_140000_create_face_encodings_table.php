<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng face_encodings — lưu face encoding vectors (128-dim từ dlib).
     * Mỗi nhân viên có thể đăng ký nhiều ảnh (3-5 ảnh, nhiều góc) để tăng accuracy.
     */
    public function up(): void
    {
        Schema::create('face_encodings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type', 10)->default('admin'); // 'admin' | 'user'
            $table->text('encoding');           // JSON array: 128-dim float vector từ dlib
            $table->string('image_path');       // Đường dẫn ảnh gốc dùng để encode
            $table->string('label', 50)->nullable(); // 'front', 'left', 'right', etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'user_type', 'is_active'], 'face_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_encodings');
    }
};
