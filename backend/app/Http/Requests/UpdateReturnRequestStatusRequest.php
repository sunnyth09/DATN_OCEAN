<?php

namespace App\Http\Requests;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestStatusRequest extends FormRequest
{
    /**
     * Chỉ admin/staff mới được xử lý yêu cầu hoàn hàng.
     * Route đã có middleware role, FormRequest vẫn verify để defense-in-depth.
     */
    public function authorize(): bool
    {
        $user = auth('admin')->user() ?? auth('api')->user();

        return $user && in_array($user->role, ['admin', 'staff'], true);
    }

    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();
        $refundMethods = Rule::in(RefundMethod::returnRequestValues());

        return match ($action) {
            'approve' => [
                'admin_note' => 'nullable|string|max:1000',
                'return_tracking_code' => 'nullable|string|max:100',
                'return_carrier' => 'nullable|string|max:100',
            ],
            'reject' => [
                'admin_note' => 'required|string|max:1000',
            ],
            'returning' => [
                'admin_note' => 'nullable|string|max:1000',
                'return_tracking_code' => 'nullable|string|max:100',
                'return_carrier' => 'nullable|string|max:100',
            ],
            'received' => [
                'admin_note' => 'nullable|string|max:1000',
                'items' => 'nullable|array',
                'items.*.return_request_item_id' => 'required_with:items|integer|exists:return_request_items,id',
                'items.*.received_quantity' => 'required_with:items|integer|min:0',
            ],
            'inspect' => [
                'inspection_note' => 'nullable|string|max:2000',
                'items' => 'required|array|min:1',
                'items.*.return_request_item_id' => 'required|integer|exists:return_request_items,id',
                'items.*.qc_pass_quantity' => 'required|integer|min:0',
                'items.*.qc_fail_quantity' => 'required|integer|min:0',
                'items.*.qc_note' => 'nullable|string|max:1000',
            ],
            'refund' => [
                'admin_note' => 'nullable|string|max:1000',
                'refund_amount' => 'nullable|numeric|min:0',
                'refund_method' => ['nullable', $refundMethods],
                'idempotency_key' => 'nullable|string|max:160',
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
            'return_tracking_code.max' => 'Mã vận đơn hoàn không được vượt quá 100 ký tự.',
            'return_carrier.max' => 'Đơn vị vận chuyển không được vượt quá 100 ký tự.',
            'items.required' => 'Vui lòng nhập danh sách sản phẩm xử lý.',
            'items.*.return_request_item_id.required' => 'Sản phẩm hoàn hàng không hợp lệ.',
            'items.*.received_quantity.required_with' => 'Vui lòng nhập số lượng kho nhận.',
            'items.*.qc_pass_quantity.required' => 'Vui lòng nhập số lượng QC đạt.',
            'items.*.qc_fail_quantity.required' => 'Vui lòng nhập số lượng QC không đạt.',
            'refund_amount.numeric' => 'Số tiền hoàn phải là số hợp lệ.',
        ];
    }
}
