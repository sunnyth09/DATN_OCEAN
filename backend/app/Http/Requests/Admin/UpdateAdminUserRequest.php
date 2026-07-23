<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'full_name' => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,' . $id . ',user_id',
            'phone'     => ['nullable', 'string', 'regex:/^(0[0-9]{9})$/', 'unique:users,phone,' . $id . ',user_id'],
            'password'  => [
                'nullable',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
            'role'      => 'nullable|in:customer,seller,staff,admin',
            'status'    => 'nullable|in:active,inactive,banned',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'   => 'Email này đã được sử dụng!',
            'password.min'   => 'Mật khẩu tối thiểu 8 ký tự!',
            'password.regex' => 'Mật khẩu phải chứa ít nhất 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt!',
            'phone.regex'    => 'Số điện thoại không hợp lệ (gồm 10 số và bắt đầu bằng 0)!',
            'phone.unique'   => 'Số điện thoại này đã được sử dụng!',
        ];
    }

    /**
     * Giữ nguyên response shape cũ để không phá client.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
