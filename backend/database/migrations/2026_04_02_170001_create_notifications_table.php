<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Tạo bảng notifications (chuẩn Laravel)
 *
 * Bảng này được Laravel sử dụng khi gửi notification qua kênh 'database'.
 * Mỗi notification sẽ được lưu dưới dạng JSON, bao gồm:
 * - type: Tên class notification (VD: App\Notifications\BirthdayNotification)
 * - notifiable_type / notifiable_id: Polymorphic relation → liên kết tới User
 * - data: JSON chứa nội dung notification (tiêu đề, message, coupon code...)
 * - read_at: Thời gian user đánh dấu đã đọc (null = chưa đọc)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();                    // UUID thay vì auto-increment
            $table->string('type');                            // Tên class Notification
            $table->morphs('notifiable');                      // notifiable_type + notifiable_id (polymorphic)
            $table->text('data');                               // JSON data chứa nội dung thông báo
            $table->timestamp('read_at')->nullable();          // Thời gian đã đọc
            $table->timestamps();                              // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
