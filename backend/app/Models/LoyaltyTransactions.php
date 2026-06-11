<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransactions extends Model
{
    protected $table = "loyalty_transactions";

    protected $primaryKey = "id";

    protected $fillable = [
        'transaction_id',
        'user_id',
        'points',
        'type',
        'product_id',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'expires_at',
        'created_at'
    ];
}
