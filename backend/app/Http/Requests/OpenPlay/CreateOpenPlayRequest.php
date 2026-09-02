<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;

class CreateOpenPlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('api')->check();
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:court_bookings,booking_id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'sport_type' => 'nullable|string|max:50',
            'skill_level' => 'required|in:beginner,intermediate,advanced,all_levels',
            'gender_rule' => 'required|in:any,male_only,female_only,mixed',
            'match_type' => 'required|in:singles,doubles,practice,casual',
            'max_players' => 'required|integer|min:2|max:12',
            'join_mode' => 'required|in:auto,approval',
            'payment_mode' => 'required|in:host_pays,split_payment',
            'rules' => 'nullable|string|max:2000',
        ];
    }
}
