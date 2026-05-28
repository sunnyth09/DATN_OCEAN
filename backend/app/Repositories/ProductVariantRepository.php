<?php

namespace App\Repositories;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantRepository
{
    public function lockVariants(array $variantIds)
    {
        return ProductVariant::whereIn('variant_id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('variant_id');
    }

    public function decrementStock(int $variantId, int $quantity): void
    {
        ProductVariant::where('variant_id', $variantId)
            ->decrement('stock', $quantity);
    }

    public function restoreStockFromOrderItems($orderItems): void
    {
        $cases = [];
        $bindings = [];
        $variantIds = [];

        foreach ($orderItems as $item) {
            $cases[] = "WHEN ? THEN stock + ?";
            $bindings[] = $item->variant_id;
            $bindings[] = $item->quantity;
            $variantIds[] = $item->variant_id;
        }

        if (empty($variantIds)) {
            return;
        }

        $ids = implode(',', array_fill(0, count($variantIds), '?'));
        $casesSql = implode(' ', $cases);
        $bindings = array_merge($bindings, $variantIds);

        DB::statement(
            "UPDATE product_variants 
             SET stock = CASE variant_id {$casesSql} END, updated_at = NOW() 
             WHERE variant_id IN ({$ids})",
            $bindings
        );
    }
}