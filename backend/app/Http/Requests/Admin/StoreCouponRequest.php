<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route đã bọc admin middleware; ủy quyền ở tầng route.
        return true;
    }

    public function rules(): array
    {
        $maxValue = $this->input('type') === 'percent' ? '|max:100' : '';

        return [
            'code' => 'required|string|max:20|unique:coupons,code',
            'type' => 'required|in:percent,fixed,free_ship',
            'value' => 'required|numeric|min:0'.$maxValue,
            'max_discount_value' => 'nullable|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'user_usage_limit' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_first_order' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,category_id',
            'send_email' => 'boolean',
        ];
    }
}
