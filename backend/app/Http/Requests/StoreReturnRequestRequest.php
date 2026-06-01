<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|image|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do hoàn hàng.',
            'images.*.image' => 'Mỗi ảnh minh chứng phải là tệp hình ảnh hợp lệ.',
            'images.*.max' => 'Mỗi ảnh minh chứng không được vượt quá 5MB.',
        ];
    }
}
