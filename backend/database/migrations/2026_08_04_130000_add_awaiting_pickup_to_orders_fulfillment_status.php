<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Thêm trạng thái `awaiting_pickup` — "đã tạo vận đơn, chờ hãng đến lấy hàng".
 *
 * TRƯỚC ĐÂY khi tạo vận đơn thành công hệ thống đặt luôn `shipping`. Điều đó
 * sai về nghiệp vụ (hàng vẫn nằm trong kho) và gây hệ quả kỹ thuật: webhook
 * `picking` thật của hãng có trọng số thấp hơn `shipping` nên bị bỏ qua âm thầm.
 */
return new class extends Migration
{
    private const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'packing',
        'awaiting_pickup',
        'shipping',
        'delivered',
        'completed',
        'cancelled',
        'return_requested',
        'return_approved',
        'return_rejected',
        'returning',
        'warehouse_received',
        'inspection_failed',
        'inspected_ok',
        'returned',
        'refunded',
    ];

    private const STATUSES_WITHOUT_AWAITING_PICKUP = [
        'pending',
        'confirmed',
        'processing',
        'packing',
        'shipping',
        'delivered',
        'completed',
        'cancelled',
        'return_requested',
        'return_approved',
        'return_rejected',
        'returning',
        'warehouse_received',
        'inspection_failed',
        'inspected_ok',
        'returned',
        'refunded',
    ];

    public function up(): void
    {
        // sqlite (dùng cho test) không có kiểu ENUM — cột là TEXT nên không cần đổi.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->modifyEnum(self::STATUSES);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Không thể giữ giá trị không còn nằm trong ENUM → lùi về `packing`,
        // trạng thái ngay trước đó trong luồng.
        DB::table('orders')
            ->where('fulfillment_status', 'awaiting_pickup')
            ->update(['fulfillment_status' => 'packing']);

        $this->modifyEnum(self::STATUSES_WITHOUT_AWAITING_PICKUP);
    }

    private function modifyEnum(array $statuses): void
    {
        $values = implode(', ', array_map(fn ($s) => "'{$s}'", $statuses));

        DB::statement("
            ALTER TABLE orders
            MODIFY fulfillment_status ENUM({$values}) NOT NULL DEFAULT 'pending'
        ");
    }
};
