<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'old_status',
        'new_status',
        'note',
        'changed_by',
        'ghn_status',
        'source',
        'location',
        'description',
        'latitude',
        'longitude',
        'happened_at',
        'created_at',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
