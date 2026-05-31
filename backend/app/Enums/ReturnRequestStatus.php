<?php

namespace App\Enums;

enum ReturnRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECEIVED = 'received';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Đã từ chối',
            self::RECEIVED => 'Đã nhận hàng hoàn',
            self::REFUNDED => 'Đã hoàn tiền',
        };
    }

    public static function activeValues(): array
    {
        return [
            self::PENDING->value,
            self::APPROVED->value,
            self::RECEIVED->value,
        ];
    }
}
