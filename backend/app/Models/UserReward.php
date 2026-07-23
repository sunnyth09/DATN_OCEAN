<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReward extends Model
{
    protected $fillable = [
        'user_id',
        'reward_id',
        'points_spent',
        'status',
    ];

    protected $casts = [
        'points_spent' => 'integer',
    ];

    public function reward()
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
