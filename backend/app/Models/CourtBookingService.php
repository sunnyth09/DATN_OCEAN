<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtBookingService extends Model
{
    use HasFactory;

    protected $primaryKey = 'booking_service_id';

    protected $fillable = [
        'booking_id', 'service_id', 'quantity', 'unit_price',
        'subtotal', 'note', 'added_by',
    ];

    public function booking()
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }

    public function service()
    {
        return $this->belongsTo(CourtService::class, 'service_id', 'service_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(Admin::class, 'added_by', 'admin_id');
    }
}
