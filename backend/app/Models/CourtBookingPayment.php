<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtBookingPayment extends Model
{
    use HasFactory;

    protected $primaryKey = 'court_payment_id';

    protected $fillable = [
        'booking_id', 'payment_type', 'payment_method', 'transaction_code',
        'amount', 'status', 'paid_at', 'gateway_response', 'note', 'processed_by',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'gateway_response' => 'json',
    ];

    public function booking()
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(Admin::class, 'processed_by', 'admin_id');
    }
}
