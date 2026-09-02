<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FIX C1: API Resource lọc thông tin user profile trả về cho client.
 * Chỉ trả các field cần thiết, loại bỏ data nhạy cảm như
 * google_id, facebook_id, reward_points, referral_code, v.v.
 */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Primary key — hỗ trợ cả User (user_id) và Admin (admin_id)
            'user_id' => $this->user_id ?? $this->admin_id ?? $this->getKey(),
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'date_of_birth' => $this->date_of_birth,
            'status' => $this->status ?? 'active',
            'role' => $this->role ?? 'customer',
            'total_spent' => (float) ($this->total_spent ?? 0),
            'tier_id' => $this->tier_id,
            'tier' => $this->tier ? [
                'name' => $this->tier->name,
                'min_spent' => (float) $this->tier->min_spent,
                'discount_percent' => (float) $this->tier->discount_percent,
                'color' => $this->tier->color,
                'icon_url' => $this->tier->icon_url,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
