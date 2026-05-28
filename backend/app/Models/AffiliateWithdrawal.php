<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
