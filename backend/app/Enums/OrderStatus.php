<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case PACKING = 'packing';
    case SHIPPING = 'shipping';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RETURN_REQUESTED = 'return_requested';
    case RETURN_APPROVED = 'return_approved';
    case RETURN_REJECTED = 'return_rejected';
    case RETURNING = 'returning';
    case WAREHOUSE_RECEIVED = 'warehouse_received';
    case INSPECTION_FAILED = 'inspection_failed';
    case INSPECTED_OK = 'inspected_ok';
    case RETURNED = 'returned';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xác nhận',
            self::CONFIRMED => 'Đã xác nhận',
            self::PROCESSING,
            self::PACKING => 'Đang xử lý',
            self::SHIPPING => 'Đang giao hàng',
            self::DELIVERED => 'Đã giao hàng',
            self::COMPLETED => 'Hoàn thành',
            self::CANCELLED => 'Đã hủy',
            self::RETURN_REQUESTED => 'Yêu cầu hoàn hàng',
            self::RETURN_APPROVED => 'Đã duyệt hoàn hàng',
            self::RETURN_REJECTED => 'Từ chối hoàn hàng',
            self::RETURNING => 'Khách đang gửi hàng hoàn',
            self::WAREHOUSE_RECEIVED => 'Kho đã nhận hàng hoàn',
            self::INSPECTION_FAILED => 'Hoàn hàng không đạt kiểm tra',
            self::INSPECTED_OK => 'Hoàn hàng đạt kiểm tra',
            self::RETURNED => 'Đã nhận hàng hoàn',
            self::REFUNDED => 'Đã hoàn tiền',
        };
    }

    public static function returnEligibleValues(): array
    {
        return [
            self::COMPLETED->value,
            self::DELIVERED->value,
        ];
    }

    public static function pendingLikeValues(): array
    {
        return [
            self::PENDING->value,
            self::CONFIRMED->value,
            self::PROCESSING->value,
            self::PACKING->value,
        ];
    }

    public static function revenueExcludedValues(): array
    {
        return [
            self::CANCELLED->value,
            self::RETURN_APPROVED->value,
            self::RETURNING->value,
            self::WAREHOUSE_RECEIVED->value,
            self::INSPECTION_FAILED->value,
            self::INSPECTED_OK->value,
            self::RETURNED->value,
            self::REFUNDED->value,
        ];
    }
}
