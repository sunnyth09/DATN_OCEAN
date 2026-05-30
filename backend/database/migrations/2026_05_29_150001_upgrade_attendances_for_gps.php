<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nâng cấp bảng attendances để hỗ trợ chấm công GPS đa chi nhánh.
     * Thêm: user_type, work_location_id, work_date, accuracy, distance, check-out GPS, status.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Phân biệt admin vs user (vì hệ thống có 2 bảng user)
            $table->string('user_type', 10)->default('admin')->after('user_id');

            // Liên kết vị trí làm việc
            $table->unsignedBigInteger('work_location_id')->nullable()->after('user_type');

            // Ngày làm việc (dễ query, index) — tách riêng khỏi check_in_at
            $table->date('work_date')->nullable()->after('work_location_id');

            // GPS accuracy cho check-in (đơn vị: mét)
            $table->decimal('check_in_accuracy', 8, 2)->nullable()->after('longitude');
            // Khoảng cách từ vị trí check-in tới work_location (đơn vị: mét)
            $table->decimal('check_in_distance_meters', 10, 2)->nullable()->after('check_in_accuracy');

            // GPS cho check-out (hiện tại chưa lưu)
            $table->decimal('check_out_latitude', 10, 8)->nullable()->after('check_out_at');
            $table->decimal('check_out_longitude', 11, 8)->nullable()->after('check_out_latitude');
            $table->decimal('check_out_accuracy', 8, 2)->nullable()->after('check_out_longitude');
            $table->decimal('check_out_distance_meters', 10, 2)->nullable()->after('check_out_accuracy');

            // Status: checked_in, checked_out, late, invalid_location, missing_checkout
            $table->string('status', 20)->default('checked_in')->after('check_out_distance_meters');

            // Indexes cho query hiệu quả
            $table->index('work_date');
            $table->index('status');
            $table->index(['user_id', 'user_type', 'work_date'], 'att_user_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('att_user_date_idx');
            $table->dropIndex(['work_date']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'user_type',
                'work_location_id',
                'work_date',
                'check_in_accuracy',
                'check_in_distance_meters',
                'check_out_latitude',
                'check_out_longitude',
                'check_out_accuracy',
                'check_out_distance_meters',
                'status',
            ]);
        });
    }
};
