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
            'items'               => 'required|array|min:1',
            'items.*.variant_id'  => 'required|integer|exists:product_variants,variant_id',
            'items.*.quantity'    => 'required|integer|min:1',
            'customer_name'       => 'nullable|string|max:255',
            'customer_phone'      => 'nullable|string|max:20',
            'payment_method'      => 'nullable|string|in:pos_cash,pos_transfer,pos_card',
            'note'                => 'nullable|string|max:500',
            'discount_amount'     => 'nullable|numeric|min:0',
        ];
    }
}
