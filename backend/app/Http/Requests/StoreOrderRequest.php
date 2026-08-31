<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Chỉ customer (api guard) mới được tạo đơn hàng.
     * Admin/staff sử dụng POS hoặc admin panel riêng.
     */
    public function authorize(): bool
    {
        return auth('api')->check() && ! auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'address_id' => 'required_without:recipient_name|nullable|exists:addresses,address_id',

            'recipient_name' => 'required_without:address_id|nullable|string|min:2|max:120',
            'phone' => ['required_without:address_id', 'nullable', 'string', 'max:20', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'province' => 'required_without:address_id|nullable|string|max:120',
            // Quận/Huyện không bắt buộc — Ocean Express chỉ dùng Tỉnh và Phường/Xã
            'district' => 'nullable|string|max:120',
            'ward' => 'required_without:address_id|nullable|string|max:120',
            'address_line' => 'required_without:address_id|nullable|string|min:5|max:255',

            'province_code' => 'nullable',
            'district_code' => 'nullable',
            'ward_code' => 'nullable',

            // Email nhận xác nhận đơn hàng (tùy chọn; mặc định dùng email tài khoản)
            'email' => 'nullable|email|max:255',

            // Mua nhanh (Buy Now): đặt trực tiếp sản phẩm, không lấy từ giỏ
            'items' => 'nullable|array|min:1',
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,variant_id',
            'items.*.quantity' => 'required_with:items|integer|min:1',

            'payment_method' => 'required|in:cod,vnpay,momo,bank_transfer,wallet',
            'coupon_applied' => 'nullable|string',
            'note' => 'nullable|string|max:500',
            'referral_code' => 'nullable|string|max:20',

            // Wallet discount
            'use_deposit' => 'nullable|boolean',
            'use_commission' => 'nullable|boolean',
            'wallet_amount' => 'nullable|numeric|min:0',

            // Loyalty points
            'reward_points_used' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required_without' => 'Vui lòng nhập họ tên người nhận.',
            'recipient_name.min' => 'Họ tên phải có ít nhất 2 ký tự.',
            'recipient_name.max' => 'Họ tên không được vượt quá 120 ký tự.',
            'phone.required_without' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'province.required_without' => 'Vui lòng chọn Tỉnh/Thành phố.',
            // district không còn required
            'ward.required_without' => 'Vui lòng chọn Phường/Xã.',
            'address_line.required_without' => 'Vui lòng nhập địa chỉ cụ thể.',
            'address_line.min' => 'Địa chỉ cụ thể quá ngắn, vui lòng nhập số nhà/tên đường.',
            'address_line.max' => 'Địa chỉ cụ thể không được vượt quá 255 ký tự.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'reward_points_used.integer' => 'Số điểm thưởng không hợp lệ.',
            'reward_points_used.min' => 'Số điểm thưởng không thể nhỏ hơn 0.',
        ];
    }
}
