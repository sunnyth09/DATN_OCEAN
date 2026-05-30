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
        Schema::create('court_bookings', function (Blueprint $table) {
            $table->id('booking_id');
            $table->string('booking_code', 30)->unique();            // VD: "BK-20260527-0001"

            // AI/WHO
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('staff_id')->nullable();      // Lễ tân tạo hộ
            $table->foreign('staff_id')->references('admin_id')->on('admins')->nullOnDelete();

            // WHAT/WHERE
            $table->unsignedBigInteger('court_id');
            $table->foreign('court_id')->references('court_id')->on('courts')->restrictOnDelete();

            // WHEN
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');

            // TRẠNG THÁI
            $table->enum('status', [
                'pending',      // Chờ xác nhận
                'confirmed',    // Đã xác nhận
                'checked_in',   // Đã nhận sân
                'playing',      // Đang chơi
                'completed',    // Hoàn thành
                'cancelled',    // Huỷ
                'no_show',      // Không đến
                'extended',     // Đã gia hạn
            ])->default('pending');

            // TIỀN
            $table->integer('original_price');
            $table->integer('discount_amount')->default(0);
            $table->integer('service_amount')->default(0);
            $table->integer('total_amount');
            $table->integer('deposit_amount')->default(0);
            $table->integer('paid_amount')->default(0);

            // THANH TOÁN
            $table->enum('payment_status', [
                'unpaid', 'deposit_paid', 'partially_paid',
                'paid', 'refunded', 'partially_refunded',
            ])->default('unpaid');
            $table->enum('payment_method', [
                'cash', 'vnpay', 'momo', 'bank_transfer', 'pos_card', 'pos_transfer',
            ])->nullable();

            // THỜI GIAN THỰC
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            // HUỶ / GHI CHÚ
            $table->enum('cancel_reason_type', [
                'customer_request', 'no_show', 'court_issue', 'maintenance', 'other',
            ])->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->text('note')->nullable();

            // NGUỒN BOOKING
            $table->enum('source', ['web', 'mobile', 'pos', 'phone'])->default('web');

            $table->timestamps();
            $table->softDeletes();

            // INDEX
            $table->index(['court_id', 'booking_date', 'status']);
            $table->index(['court_id', 'booking_date', 'start_time', 'end_time']);
            $table->index(['user_id', 'status']);
            $table->index(['booking_date', 'status']);
            $table->index('booking_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_bookings');
    }
};
