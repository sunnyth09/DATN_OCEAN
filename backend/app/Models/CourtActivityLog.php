<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtActivityLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'log_id';

    public $timestamps = false; // Only created_at is used

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'subject_type',
        'subject_id', 'old_data', 'new_data', 'ip_address',
        'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
        'created_at' => 'datetime',
    ];
}
