<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUND_PENDING = 'refund_pending';
    case REFUNDED = 'refunded';
    case REFUND_FAILED = 'refund_failed';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Chưa thanh toán',
            self::PAID => 'Đã thanh toán',
            self::FAILED => 'Thanh toán thất bại',
            self::REFUND_PENDING => 'Chờ hoàn tiền',
            self::REFUNDED => 'Đã hoàn tiền',
            self::REFUND_FAILED => 'Hoàn tiền thất bại',
            self::PARTIALLY_REFUNDED => 'Hoàn một phần',
        };
    }

    public static function refundableValues(): array
    {
        return [
            self::PAID->value,
            self::REFUND_PENDING->value,
            self::PARTIALLY_REFUNDED->value,
        ];
    }
}
