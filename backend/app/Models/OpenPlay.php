<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpenPlay extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'open_plays';

    protected $fillable = [
        'open_play_code',
        'booking_id',
        'host_user_id',
        'title',
        'description',
        'sport_type',
        'skill_level',
        'gender_rule',
        'match_type',
        'max_players',
        'current_players',
        'join_mode',
        'payment_mode',
        'slot_price',
        'status',
        'rules',
    ];

    protected $casts = [
        'max_players' => 'integer',
        'current_players' => 'integer',
        'slot_price' => 'integer',
    ];

    protected $appends = [
        'available_slots',
    ];

    public function getAvailableSlotsAttribute(): int
    {
        return max(0, (int) $this->max_players - (int) $this->current_players);
    }

    public function booking()
    {
        return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id', 'user_id');
    }

    public function participants()
    {
        return $this->hasMany(OpenPlayParticipant::class, 'open_play_id');
    }

    public function confirmedParticipants()
    {
        return $this->hasMany(OpenPlayParticipant::class, 'open_play_id')
            ->whereIn('status', ['confirmed', 'checked_in', 'completed']);
    }

    public function waitlists()
    {
        return $this->hasMany(OpenPlayWaitlist::class, 'open_play_id');
    }

    public function activeWaitlists()
    {
        return $this->hasMany(OpenPlayWaitlist::class, 'open_play_id')
            ->where('status', 'waiting')
            ->orderBy('position', 'asc');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'full', 'ongoing']);
    }

    public function scopeOpenForJoin($query)
    {
        return $query->where('status', 'open');
    }
}
