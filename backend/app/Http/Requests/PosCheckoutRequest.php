<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route đã bọc auth admin/staff; ủy quyền ở tầng route.
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'payment_method' => 'nullable|string|in:pos_cash,pos_transfer,pos_card',
            'note' => 'nullable|string|max:500',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:100',
            'coupon_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Vui lòng nhập tên khách hàng',
            'customer_name.string' => 'Tên khách hàng phải là chuỗi ký tự',
            'customer_name.max' => 'Tên khách hàng không được vượt quá 255 ký tự',
        ];
    }
}
