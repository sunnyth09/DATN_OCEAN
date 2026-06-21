<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class StoreOrderRequest  extends FormRequest
{
    /**
     * Chỉ customer (api guard) mới được tạo đơn hàng.
     * Admin/staff sử dụng POS hoặc admin panel riêng.
     */
    public function authorize(): bool
    {
        return auth('api')->check() && !auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'address_id' => 'required_without:recipient_name|nullable|exists:addresses,address_id',

            'recipient_name' => 'required_without:address_id|nullable|string|max:255',
            'phone' => 'required_without:address_id|nullable|string|max:20',
            'province' => 'required_without:address_id|nullable|string|max:100',
            'district' => 'required_without:address_id|nullable|string|max:100',
            'ward' => 'required_without:address_id|nullable|string|max:100',
            'address_line' => 'required_without:address_id|nullable|string|max:255',

            'province_code' => 'nullable',
            'district_code' => 'nullable',
            'ward_code' => 'nullable',

            'payment_method' => 'required|in:cod,vnpay,momo,bank_transfer,wallet',
            'coupon_applied' => 'nullable|string',
            'note' => 'nullable|string|max:500',
            'referral_code' => 'nullable|string|max:20',
        ];
    }
    
    public function messages(): array
    {
        return [            
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
        ];
    }
}