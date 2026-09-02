<?php

namespace App\Http\Requests\OpenPlay;

use Illuminate\Foundation\Http\FormRequest;

class JoinOpenPlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => 'nullable|string|max:100',
            'guest_phone' => 'nullable|string|max:20',
            'note' => 'nullable|string|max:255',
        ];
    }
}
