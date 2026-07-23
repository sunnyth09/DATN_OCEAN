<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backstop tầng DB chống double check-in: mỗi (nhân viên, ngày, ca) chỉ 1 bản ghi.
     * Cache::lock ở AttendanceService chỉ chặn double-submit trong 10s; unique index
     * mới là ràng buộc thật khi có race/replay vượt qua lock.
     */
    public function up(): void
    {
        // Dọn bản ghi trùng (nếu có) trước khi thêm unique index, nếu không migration sẽ fail.
        // Giữ lại bản ghi cũ nhất (id nhỏ nhất) cho mỗi bộ khóa.
        $duplicates = DB::table('attendances')
            ->select('user_id', 'user_type', 'work_date', 'work_shift_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('work_shift_id')
            ->groupBy('user_id', 'user_type', 'work_date', 'work_shift_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('attendances')
                ->where('user_id', $dup->user_id)
                ->where('user_type', $dup->user_type)
                ->where('work_date', $dup->work_date)
                ->where('work_shift_id', $dup->work_shift_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'user_type', 'work_date', 'work_shift_id'],
                'att_user_shift_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('att_user_shift_date_unique');
        });
    }
};
