<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceEncoding extends Model
{
    protected $table = 'face_encodings';

    protected $fillable = [
        'user_id',
        'user_type',
        'encoding',
        'image_path',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'encoding'  => 'array', // JSON array 128-dim vector
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: Lấy encodings active của một user cụ thể.
     */
    public function scopeOfUser($query, int $userId, string $userType)
    {
        return $query->where('user_id', $userId)
                     ->where('user_type', $userType)
                     ->where('is_active', true);
    }
}
