<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOpenPlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('api')->check();
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:1000',
            'skill_level' => 'sometimes|in:beginner,intermediate,advanced,all_levels',
            'gender_rule' => 'sometimes|in:any,male_only,female_only,mixed',
            'match_type' => 'sometimes|in:singles,doubles,practice,casual',
            'rules' => 'nullable|string|max:2000',
        ];
    }
}
