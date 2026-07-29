<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;

class CartRepository
{
    public function getActiveCart(int $userId)
    {
        return Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    public function getSelectedCartItems(int $cartId)
    {
        return CartItem::with(['variant.product'])
            ->where('cart_id', $cartId)
            ->where('selected', true)
            ->get();
    }

    public function deleteItems(array $cartItemIds): int
    {
        return CartItem::whereIn('cart_item_id', $cartItemIds)->delete();
    }

    public function getOrCreateActiveCart(int $userId): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            ['user_id' => $userId, 'status' => 'active']
        );
    }
}
