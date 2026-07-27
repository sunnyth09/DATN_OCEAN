<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $primaryKey = 'order_id';

    protected $fillable = [
        'order_code',
        'ghn_order_code',
        'tracking_token',
        'user_id',
        'seller_id',
        'address_id',
        'promotion_id',
        'recipient_name',
        'recipient_phone',
        'email',
        'shipping_address',
        'province_code',
        'district_code',
        'ward_code',
        'note',
        'payment_method',
        'payment_status',
        'fulfillment_status',
        'subtotal',
        'discount_amount',
        'wallet_deposit_discount',
        'wallet_commission_discount',
        'shipping_fee',
        'grand_total',
        'email_sent',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'combo_discount',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'wallet_deposit_discount' => 'decimal:2',
        'wallet_commission_discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'combo_discount' => 'decimal:2',
        'email_sent' => 'boolean',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', 'order_id');
    }

    public function seller()
    {
        return $this->belongsTo(Admin::class, 'seller_id', 'admin_id');
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class, 'order_id', 'order_id');
    }

    public function getOrderId($order_code)
    {
        $order = $this->where('order_code', $order_code)->first();

        return $order->order_id;
    }

    public function affiliateConversion()
    {
        return $this->hasOne(AffiliateConversion::class, 'order_id', 'order_id');
    }
}
