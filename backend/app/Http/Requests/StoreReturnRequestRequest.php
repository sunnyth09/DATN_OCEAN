<?php

namespace App\Http\Requests;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequestRequest extends FormRequest
{
    /**
     * Chỉ customer đăng nhập mới được tạo yêu cầu hoàn hàng.
     * Logic verify đơn hàng thuộc về user được kiểm tra trong ReturnRequestService.
     */
    public function authorize(): bool
    {
        return auth('api')->check() && !auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'refund_method' => ['required', Rule::in(array_map(
                static fn (RefundMethod $method) => $method->value,
                RefundMethod::cases()
            ))],
            'idempotency_key' => 'nullable|string|max:120',
            'return_shipping_method' => ['required', Rule::in(['pickup_original_address', 'dropoff_post_office'])],
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,order_item_id',
            'items.*.quantity' => 'required|integer|min:1',
            'images' => 'nullable|array|max:5',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',
            'videos' => 'nullable|array|max:1',
            'videos.*' => 'file|mimes:mp4,mov,avi,webm|max:51200',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do hoàn hàng.',
            'refund_method.required' => 'Vui lòng chọn phương thức hoàn tiền.',
            'return_shipping_method.required' => 'Vui lòng chọn cách gửi hàng hoàn.',
            'return_shipping_method.in' => 'Cách gửi hàng hoàn không hợp lệ.',
            'items.required' => 'Vui lòng chọn sản phẩm cần hoàn.',
            'items.*.order_item_id.required' => 'Sản phẩm hoàn hàng không hợp lệ.',
            'items.*.quantity.required' => 'Vui lòng nhập số lượng hoàn.',
            'items.*.quantity.min' => 'Số lượng hoàn phải lớn hơn 0.',
            'images.max' => 'Chỉ được tải lên tối đa 5 ảnh minh chứng.',
            'images.*.mimes' => 'Ảnh minh chứng chỉ hỗ trợ JPG, PNG hoặc WEBP.',
            'images.*.max' => 'Mỗi ảnh minh chứng không được vượt quá 10MB.',
            'videos.max' => 'Chỉ được tải lên tối đa 1 video minh chứng.',
            'videos.*.mimes' => 'Video minh chứng chỉ hỗ trợ MP4, MOV, AVI hoặc WEBM.',
            'videos.*.max' => 'Video minh chứng không được vượt quá 50MB.',
        ];
    }
}
