<?php

namespace App\Enums;

enum RefundStatus: string
{
    case NONE = 'none';
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Chưa hoàn tiền',
            self::PENDING => 'Chờ hoàn tiền',
            self::SUCCESS => 'Đã hoàn tiền',
            self::FAILED => 'Hoàn tiền thất bại',
        };
    }
}
