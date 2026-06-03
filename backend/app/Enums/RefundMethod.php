<?php

namespace App\Enums;

enum RefundMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';
    case VNPAY = 'vnpay';
    case MOMO = 'momo';
    case OTHER = 'other';
}
