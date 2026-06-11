<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'order_id',
        'payment_method',
        'transaction_code',
        'amount',
        'status',
        'paid_at',
        'confirmed_at',
        'confirmed_source',
        'post_payment_key',
        'post_payment_status',
        'post_payment_started_at',
        'post_payment_processed_at',
        'post_payment_source',
        'post_payment_last_error',
        'gateway_response',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'post_payment_started_at' => 'datetime',
        'post_payment_processed_at' => 'datetime',
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
