<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    protected $table = 'flash_sale_items';

    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'campaign_price',
        'campaign_stock',
        'sold',
        'min_qty',   // Số lượng tối thiểu của item này trong cart (combo mode)
    ];

    protected $casts = [
        'campaign_price' => 'float',
        'campaign_stock' => 'integer',
        'sold' => 'integer',
        'min_qty' => 'integer',
    ];

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
