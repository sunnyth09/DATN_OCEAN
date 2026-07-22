<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'admins';
    protected $primaryKey = 'admin_id';

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'status',        // FIX M8: Thêm status vào fillable
        'phone',
        'avatar_url',
        'date_of_birth', // FIX M4: Thêm date_of_birth vào fillable
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'date_of_birth' => 'date', // FIX M4: Cast date_of_birth
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
        ];
    }

    /**
     * Xác định kênh Broadcast mà model này sẽ lắng nghe Notification
     */
    public function receivesBroadcastNotificationsOn()
    {
        return 'admin-notifications';
    }
}
