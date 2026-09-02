<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenPlayWaitlist extends Model
{
    use HasFactory;

    protected $table = 'open_play_waitlists';

    protected $fillable = [
        'open_play_id',
        'user_id',
        'position',
        'status',
        'promoted_at',
        'expires_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'promoted_at' => 'datetime',
        'expires_at' => 'datetime',
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
