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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id('ticket_id');
            $table->unsignedBigInteger('user_id')->comment('Người tạo khiếu nại');
            $table->unsignedBigInteger('order_id')->nullable()->comment('Đơn hàng liên quan');
            $table->unsignedBigInteger('product_id')->nullable()->comment('Sản phẩm liên quan');
            $table->string('reason')->comment('Lý do khiếu nại');
            $table->text('description')->comment('Mô tả chi tiết');
            $table->string('image_url')->nullable()->comment('Ảnh minh chứng');
            $table->enum('status', ['pending', 'processing', 'resolved', 'closed'])->default('pending')->comment('Trạng thái xử lý');
            $table->text('admin_reply')->nullable()->comment('Phản hồi từ admin');
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('order_id')->on('orders')->onDelete('set null');
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
