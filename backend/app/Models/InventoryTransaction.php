<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $primaryKey = 'inventory_transaction_id';

    // Bảng chỉ có created_at (useCurrent), không có updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'variant_id',
        'transaction_type',
        'quantity',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reference_id' => 'integer',
    ];
}
