<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpenPlayParticipant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'open_play_participants';

    protected $fillable = [
        'open_play_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'role',
        'status',
        'payment_status',
        'payment_amount',
        'payment_method',
        'payment_transaction_code',
        'joined_at',
        'approved_at',
        'cancelled_at',
        'cancel_reason',
        'checked_in_at',
        'check_in_token',
    ];

    protected $casts = [
        'payment_amount' => 'integer',
        'joined_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public function openPlay()
    {
        return $this->belongsTo(OpenPlay::class, 'open_play_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
