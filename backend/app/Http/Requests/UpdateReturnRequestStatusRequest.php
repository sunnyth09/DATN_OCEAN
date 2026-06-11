<?php

namespace App\Http\Requests;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestStatusRequest extends FormRequest
{
    /**
     * Chỉ admin/staff mới được xử lý yêu cầu hoàn hàng.
     * Route đã có middleware 'role:admin,staff' nhưng FormRequest cũng verify để defense-in-depth.
     */
    public function authorize(): bool
    {
        return auth('admin')->check()
            && in_array(auth('admin')->user()->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();

        return match ($action) {
            'approve' => [
                'admin_note' => 'nullable|string|max:1000',
            ],
            'reject' => [
                'admin_note' => 'required|string|max:1000',
            ],
            'refund' => [
                'admin_note' => 'nullable|string|max:1000',
                'refund_amount' => 'required|numeric|min:0',
                'refund_method' => ['required', Rule::in(array_map(
                    static fn (RefundMethod $method) => $method->value,
                    RefundMethod::cases()
                ))],
            ],
            default => [
                'admin_note' => 'nullable|string|max:1000',
            ],
        };
    }

    public function messages(): array
    {
        return [
            'admin_note.required' => 'Vui lòng nhập ghi chú xử lý.',
            'refund_amount.required' => 'Vui lòng nhập số tiền hoàn.',
            'refund_amount.numeric' => 'Số tiền hoàn phải là số hợp lệ.',
            'refund_method.required' => 'Vui lòng chọn phương thức hoàn tiền.',
        ];
    }
}
