<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuyFlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth kiểm tra ở controller (hỗ trợ cả guard api + admin); route đã bọc auth middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'flash_sale_id'   => 'required|integer|exists:flash_sales,id',
            'product_id'      => 'required|integer|exists:products,product_id',
            'quantity'        => 'integer|min:1|max:5',
            'recipient_name'  => 'required|string|max:100',
            'recipient_phone' => 'required|string|max:20',
        ];
    }
}
