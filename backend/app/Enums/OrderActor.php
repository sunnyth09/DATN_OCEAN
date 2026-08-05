<?php

namespace App\Enums;

/**
 * Chủ thể thực hiện chuyển trạng thái đơn hàng.
 *
 * Đây là khái niệm cốt lõi của OrderStateMachine: cùng một transition có thể
 * hợp lệ với chủ thể này nhưng bị chặn với chủ thể khác. Ví dụ `shipping` chỉ
 * được đặt bởi CARRIER (webhook / sync API của hãng vận chuyển), Admin không
 * có quyền tự bấm — vì hàng chưa chắc đã rời kho.
 */
enum OrderActor: string
{
    /** Người dùng trang quản trị (admin/seller) bấm nút đổi trạng thái. */
    case ADMIN = 'admin';

    /** Hãng vận chuyển — webhook hoặc command polling trạng thái từ API hãng. */
    case CARRIER = 'carrier';

    /**
     * Hệ thống: job, scheduler, cổng thanh toán, luồng đặt hàng của khách.
     * SYSTEM là chủ thể tin cậy nhất nhưng vẫn KHÔNG được vượt quyền CARRIER
     * ở các trạng thái giao vận.
     */
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Quản trị viên',
            self::CARRIER => 'Hãng vận chuyển',
            self::SYSTEM => 'Hệ thống',
        };
    }
}
