<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'product_id',
        'variant_id',
        'ordered_quantity',
        'requested_quantity',
        'received_quantity',
        'qc_pass_quantity',
        'qc_fail_quantity',
        'unit_price',
        'refundable_amount',
        'qc_status',
        'qc_note',
        'inventory_updated_at',
    ];

    protected $casts = [
        'ordered_quantity' => 'integer',
        'requested_quantity' => 'integer',
        'received_quantity' => 'integer',
        'qc_pass_quantity' => 'integer',
        'qc_fail_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'refundable_amount' => 'decimal:2',
        'inventory_updated_at' => 'datetime',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'variant_id');
    }
}
