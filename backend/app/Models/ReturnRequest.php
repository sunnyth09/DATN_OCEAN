<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'return_code',
        'order_id',
        'user_id',
        'reason',
        'description',
        'images',
        'videos',
        'status',
        'admin_note',
        'reject_reason',
        'inspection_note',
        'return_tracking_code',
        'return_carrier',
        'return_ghn_order_code',
        'return_ghn_response',
        'return_label_created_at',
        'return_shipping_method',
        'return_pickup_name',
        'return_pickup_phone',
        'return_pickup_address',
        'return_pickup_province_code',
        'return_pickup_district_code',
        'return_pickup_ward_code',
        'idempotency_key',
        'refund_amount',
        'refund_method',
        'refund_status',
        'requested_at',
        'approved_at',
        'rejected_at',
        'returning_at',
        'received_at',
        'warehouse_received_at',
        'inspected_at',
        'refund_started_at',
        'refund_failed_at',
        'refunded_at',
        'completed_at',
    ];

    protected $casts = [
        'images' => 'array',
        'videos' => 'array',
        'return_ghn_response' => 'array',
        'refund_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'return_label_created_at' => 'datetime',
        'returning_at' => 'datetime',
        'received_at' => 'datetime',
        'warehouse_received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'refund_started_at' => 'datetime',
        'refund_failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function refundTransactions()
    {
        return $this->hasMany(RefundTransaction::class);
    }
}
