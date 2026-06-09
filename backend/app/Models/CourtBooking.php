<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Notifications\Notifiable;
use App\Models\CourtBookingStatusHistory;
use App\Models\CourtBookingService;
use App\Models\CourtBookingPayment;
use App\Models\CourtBookingExtension;

class CourtBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'booking_id';

    const BLOCKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'playing', 'extended'];
    const FREE_STATUSES     = ['cancelled', 'completed', 'no_show', 'expired'];

    protected $fillable = [
        'booking_code', 'user_id', 'staff_id', 'court_id',
        'booking_date', 'start_time', 'end_time', 'duration_minutes',
        'status', 'original_price', 'discount_amount', 'service_amount',
        'total_amount', 'deposit_amount', 'paid_amount',
        'payment_status', 'payment_method',
        'customer_name', 'customer_phone', 'customer_email',
        'checked_in_at', 'checked_out_at', 'confirmed_at', 'cancelled_at',
        'cancel_reason_type', 'cancel_reason', 'note', 'source',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function staff()
    {
        return $this->belongsTo(Admin::class, 'staff_id', 'admin_id');
    }

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id', 'court_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(CourtBookingStatusHistory::class, 'booking_id', 'booking_id');
    }

    public function services()
    {
        return $this->hasMany(CourtBookingService::class, 'booking_id', 'booking_id');
    }

    public function payments()
    {
        return $this->hasMany(CourtBookingPayment::class, 'booking_id', 'booking_id');
    }

    public function extensions()
    {
        return $this->hasMany(CourtBookingExtension::class, 'booking_id', 'booking_id');
    }
}
