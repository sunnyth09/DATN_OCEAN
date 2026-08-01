<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route đã bọc admin middleware; ủy quyền ở tầng route.
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
            'phone' => ['nullable', 'string', 'regex:/^(0[0-9]{9})$/', 'unique:users,phone'],
            'role' => 'nullable|in:customer,seller,staff,admin',
            'status' => 'nullable|in:active,inactive,banned',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên là bắt buộc!',
            'email.required' => 'Email là bắt buộc!',
            'email.email' => 'Email không hợp lệ!',
            'email.unique' => 'Email này đã được sử dụng!',
            'password.required' => 'Mật khẩu là bắt buộc!',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự!',
            'password.regex' => 'Mật khẩu phải chứa ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt!',
            'phone.regex' => 'Số điện thoại không hợp lệ (gồm 10 số và bắt đầu bằng 0)!',
            'phone.unique' => 'Số điện thoại này đã được sử dụng!',
        ];
    }

    /**
     * Giữ nguyên response shape cũ của controller để không phá client:
     * { status: 'error', message: <lỗi đầu tiên>, errors: <bag> } với HTTP 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
