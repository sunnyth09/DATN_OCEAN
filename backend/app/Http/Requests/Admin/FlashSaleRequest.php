<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class FlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'status' => 'required|in:draft,active,ended',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,product_id',
            'items.*.campaign_price' => 'required|numeric|min:0',
            'items.*.campaign_stock' => 'required|integer|min:1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (! is_array($this->items)) {
                return;
            }

            foreach ($this->items as $index => $item) {
                // Kiểm tra Product
                $product = Product::with('variants')->find($item['product_id'] ?? null);
                if ($product) {
                    $basePrice = $product->min_price ?? 0;
                    $totalStock = $product->variants->sum('stock');

                    // Kiểm tra giá campaign rẻ hơn giá gốc (min_price)
                    if (isset($item['campaign_price']) && $item['campaign_price'] >= $basePrice) {
                        $validator->errors()->add("items.$index.campaign_price", 'Giá Flash sale phải nhỏ hơn giá gốc.');
                    }
                    // Kiểm tra số lượng chia cho campaign không vượt quá kho thực tế tổng của các variants
                    if (isset($item['campaign_stock']) && $item['campaign_stock'] > $totalStock) {
                        $validator->errors()->add("items.$index.campaign_stock", "Số lượng bán không vượt quá tồn thực ({$totalStock}).");
                    }
                }
            }
        });
    }
}
