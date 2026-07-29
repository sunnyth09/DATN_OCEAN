<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    /**
     * Chỉ owner của đơn hàng mới được hủy.
     * Gate::before() trong OrderPolicy cho phép admin bypass nếu cần.
     */
    public function authorize(): bool
    {
        $user = auth('api')->user();
        if (! $user) {
            return false;
        }

        $orderId = $this->route('id');
        if (! $orderId) {
            return false;
        }

        $order = Order::find($orderId);

        return $order && $user->user_id === $order->user_id;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy đơn hàng.',
        ];
    }
}
