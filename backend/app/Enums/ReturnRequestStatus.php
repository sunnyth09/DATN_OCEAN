<?php

namespace App\Enums;

enum ReturnRequestStatus: string
{
    case PENDING = 'return_pending';
    case APPROVED = 'return_approved';
    case REJECTED = 'return_rejected';
    case RETURNING = 'returning';
    case WAREHOUSE_RECEIVED = 'warehouse_received';
    case INSPECTION_FAILED = 'inspection_failed';
    case INSPECTED_OK = 'inspected_ok';
    case REFUNDING = 'refunding';
    case REFUND_PENDING = 'refund_pending';
    case REFUND_FAILED = 'refund_failed';
    case COMPLETED = 'return_completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Đã từ chối',
            self::RETURNING => 'Khách đang gửi hàng',
            self::WAREHOUSE_RECEIVED => 'Kho đã nhận hàng hoàn',
            self::INSPECTION_FAILED => 'Kiểm tra không đạt',
            self::INSPECTED_OK => 'Kiểm tra đạt',
            self::REFUNDING => 'Đang hoàn tiền',
            self::REFUND_PENDING => 'Chờ hoàn tiền',
            self::REFUND_FAILED => 'Hoàn tiền thất bại',
            self::COMPLETED => 'Hoàn hàng hoàn tất',
        };
    }

    public static function normalize(?string $status): ?string
    {
        return match ($status) {
            'pending' => self::PENDING->value,
            'approved' => self::APPROVED->value,
            'rejected' => self::REJECTED->value,
            'received' => self::WAREHOUSE_RECEIVED->value,
            'refunded' => self::COMPLETED->value,
            default => $status,
        };
    }

    public static function activeValues(): array
    {
        return [
            self::PENDING->value,
            self::APPROVED->value,
            self::RETURNING->value,
            self::WAREHOUSE_RECEIVED->value,
            self::INSPECTED_OK->value,
            self::REFUNDING->value,
            self::REFUND_PENDING->value,
            self::REFUND_FAILED->value,
            'pending',
            'approved',
            'received',
        ];
    }

    public static function terminalValues(): array
    {
        return [
            self::REJECTED->value,
            self::INSPECTION_FAILED->value,
            self::COMPLETED->value,
            'rejected',
            'refunded',
        ];
    }

    public static function refundableValues(): array
    {
        return [
            self::INSPECTED_OK->value,
            self::REFUND_PENDING->value,
            self::REFUND_FAILED->value,
        ];
    }
}
