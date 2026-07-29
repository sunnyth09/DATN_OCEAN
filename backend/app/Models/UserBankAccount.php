<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserBankAccount — Tài khoản ngân hàng đã liên kết của user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $bank_name
 * @property string|null $bank_short_name
 * @property string|null $bank_bin
 * @property string $account_name
 * @property string $account_number
 * @property bool $is_default
 */
class UserBankAccount extends Model
{
    protected $fillable = [
        'user_id',
        'bank_name',
        'bank_short_name',
        'bank_bin',
        'account_name',
        'account_number',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
