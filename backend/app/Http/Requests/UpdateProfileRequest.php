<?php

namespace App\Http\Requests;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name'     => 'required|string|max:120',
            'phone'         => [
                'nullable',
                'string',
                'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/',
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $user = $this->user() ?? auth('api')->user() ?? auth('admin')->user();
                    if ($user && $value) {
                        $dob = \Carbon\Carbon::parse($value);
                        if ($dob->greaterThan($user->created_at)) {
                            $fail('Ngày sinh không thể vượt quá thời gian tham gia hệ thống.');
                        }
                    }
                },
            ],
            'avatar'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên là bắt buộc.',
            'full_name.max' => 'Họ và tên không được dài quá 120 ký tự.',
            'phone.regex' => 'Số điện thoại không hợp lệ (phải là số điện thoại Việt Nam).',
            'avatar.image' => 'File tải lên phải là hình ảnh (jpeg, png, jpg, gif).',
            'avatar.mimes' => 'Hình ảnh phải có định dạng: jpeg, png, jpg, gif.',
            'avatar.max' => 'Kích thước hình ảnh không được vượt quá 2MB.',
            'date_of_birth.date' => 'Ngày sinh không hợp lệ.',
            'date_of_birth.before_or_equal' => 'Ngày sinh không thể vượt quá ngày hiện tại.',
        ];
    }
}
