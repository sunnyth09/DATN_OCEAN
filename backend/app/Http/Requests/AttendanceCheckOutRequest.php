<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth đã được xử lý bởi middleware
    }

    public function rules(): array
    {
        return [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'nullable|numeric|min:0|max:10000',
            'image'     => 'nullable|string|max:2097152', // ~2MB base64
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'  => 'Không thể lấy vĩ độ GPS từ thiết bị.',
            'latitude.between'   => 'Vĩ độ GPS không hợp lệ (phải từ -90 đến 90).',
            'longitude.required' => 'Không thể lấy kinh độ GPS từ thiết bị.',
            'longitude.between'  => 'Kinh độ GPS không hợp lệ (phải từ -180 đến 180).',
            'accuracy.numeric'   => 'Độ chính xác GPS phải là số.',
            'image.max'          => 'Ảnh selfie quá lớn (tối đa ~2MB).',
        ];
    }
}
