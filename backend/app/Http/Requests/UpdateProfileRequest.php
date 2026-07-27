<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // We rely on auth middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * FIX C3: date_of_birth — before:today, after:1900-01-01
     * FIX C4: phone — regex chuẩn SĐT Việt Nam
     * FIX C5: full_name — chỉ cho phép chữ cái, khoảng trắng, dấu gạch ngang, dấu chấm
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => [
                'required',
                'string',
                'max:120',
                'regex:/^[\pL\s\-\.]+$/u', // FIX C5: Chỉ cho phép Unicode letters, spaces, hyphens, dots
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^(0[2-9]|\+84[2-9])[0-9]{8,9}$/', // FIX C4: Chuẩn SĐT Việt Nam
            ],
            'date_of_birth' => 'nullable|date|before:today|after:1900-01-01', // FIX C3
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên là bắt buộc.',
            'full_name.max' => 'Họ và tên không được dài quá 120 ký tự.', // FIX L2: 255 → 120
            'full_name.regex' => 'Họ và tên chỉ được chứa chữ cái, khoảng trắng, dấu gạch ngang và dấu chấm.',
            'phone.max' => 'Số điện thoại không được dài quá 20 ký tự.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (vd: 0912345678 hoặc +84912345678).',
            'date_of_birth.date' => 'Ngày sinh không hợp lệ.',
            'date_of_birth.before' => 'Ngày sinh phải trước ngày hôm nay.',
            'date_of_birth.after' => 'Ngày sinh không hợp lệ (quá xa trong quá khứ).',
            'avatar.image' => 'File tải lên phải là hình ảnh (jpeg, png, jpg, gif).',
            'avatar.mimes' => 'Hình ảnh phải có định dạng: jpeg, png, jpg, gif.',
            'avatar.max' => 'Kích thước hình ảnh không được vượt quá 2MB.',
        ];
    }
}
