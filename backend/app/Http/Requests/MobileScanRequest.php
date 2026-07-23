<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route đã bọc auth; ủy quyền ở tầng route.
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode'    => 'required|string',
            'session_id' => 'required|string',
        ];
    }
}
