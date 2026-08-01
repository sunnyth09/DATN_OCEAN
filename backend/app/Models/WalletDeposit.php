<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletDeposit extends Model
{
    protected $table = 'wallet_deposits';

    protected $fillable = [
        'user_id',
        'deposit_code',
        'amount',
        'method',
        'status',
        'gateway_transaction_id',
        'gateway_response',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'completed_at' => 'datetime',
    ];

    // ── Relationships ──

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
