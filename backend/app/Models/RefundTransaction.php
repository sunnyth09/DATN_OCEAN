<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundTransaction extends Model
{
    protected $fillable = [
        'return_request_id',
        'order_id',
        'payment_id',
        'gateway',
        'method',
        'amount',
        'status',
        'idempotency_key',
        'gateway_refund_id',
        'gateway_response',
        'failure_reason',
        'attempt_count',
        'requested_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'attempt_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(Admin::class, 'requested_by', 'admin_id');
    }
}
