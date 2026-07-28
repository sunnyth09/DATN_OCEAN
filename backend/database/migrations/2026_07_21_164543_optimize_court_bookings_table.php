<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotent: cột deposit_amount đã được tạo ở migration gốc
     * (2026_05_28_000004_create_court_bookings_table, kiểu integer), nên chỉ thêm
     * khi thực sự thiếu. Phần mới của migration này là 2 index tối ưu truy vấn —
     * cũng chỉ thêm khi chưa tồn tại để migrate chạy lại được an toàn.
     */
    public function up(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('court_bookings', 'deposit_amount')) {
                $table->decimal('deposit_amount', 15, 2)->default(0)->after('total_amount');
            }

            if (!$this->indexExists('court_bookings', 'idx_booking_time')) {
                $table->index(['start_time', 'end_time'], 'idx_booking_time');
            }

            if (!$this->indexExists('court_bookings', 'idx_court_status')) {
                $table->index(['court_id', 'status'], 'idx_court_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Chỉ drop 2 index do migration này thêm. KHÔNG drop deposit_amount vì cột đó
     * thuộc migration tạo bảng gốc, không phải do migration này sinh ra.
     */
    public function down(): void
    {
        Schema::table('court_bookings', function (Blueprint $table) {
            if ($this->indexExists('court_bookings', 'idx_booking_time')) {
                $table->dropIndex('idx_booking_time');
            }

            if ($this->indexExists('court_bookings', 'idx_court_status')) {
                $table->dropIndex('idx_court_status');
            }
        });
    }

    /**
     * Kiểm tra một index có tồn tại trên bảng chưa (MySQL).
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("PRAGMA index_list('$table')"))
                ->contains(fn($idx) => $idx->name === $index);
        }

        $database = $connection->getDatabaseName();

        $result = $connection->selectOne(
            'SELECT COUNT(1) AS cnt FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return $result && (int) $result->cnt > 0;
    }
};
