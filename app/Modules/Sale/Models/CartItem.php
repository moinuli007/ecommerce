<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

final class CartItem extends Model
{
    protected static string $table = 'cart_items';

    /** @return array<int,array<string,mixed>> */
    public static function ofCart(int $cartId): array
    {
        return static::where('cart_id', $cartId)->orderBy('id')->get();
    }

    /** @return array<string,mixed> */
    public static function line(int $cartId, int $productId, int $variantId): array
    {
        return static::query()
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();
    }
}
