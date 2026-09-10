<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Sale\Services\CartService;
use RuntimeException;

/**
 * কার্ট API — `guard: guest`, লগইন লাগে না। টোকেন `cart_token` কুকিতে (httpOnly)
 * অথবা `X-Cart-Token` হেডারে (মোবাইল ক্লায়েন্ট) — প্রথম কলেই সার্ভার একটা বানিয়ে
 * কুকিতে বসিয়ে দেয়। doc/10-storefront-order.md §৪।
 */
final class CartApi
{
    /** GET /api/v1/storefront/cart */
    public static function show(): array
    {
        return Response::success('', CartService::details(CartTokenResolver::resolve()));
    }

    /** POST /api/v1/storefront/cart/items — {product_id, variant_id?, qty?} */
    public static function addItem(): array
    {
        $productId = Request::int('product_id');
        $variantId = Request::int('variant_id');
        $qty       = Request::float('qty') ?: 1.0;

        if ($productId <= 0) {
            return Response::error('Product is required.');
        }

        try {
            $cart = CartService::addItem(CartTokenResolver::resolve(), $productId, $variantId, $qty);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Added to cart.', $cart);
    }

    /** PUT /api/v1/storefront/cart/items/{id} — {qty} */
    public static function updateItem(): array
    {
        try {
            $cart = CartService::updateItemQty(CartTokenResolver::resolve(), Request::paramInt('id'), Request::float('qty'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Cart updated.', $cart);
    }

    /** DELETE /api/v1/storefront/cart/items/{id} */
    public static function removeItem(): array
    {
        try {
            $cart = CartService::removeItem(CartTokenResolver::resolve(), Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Removed from cart.', $cart);
    }

    /** DELETE /api/v1/storefront/cart */
    public static function clear(): array
    {
        CartService::clear(CartTokenResolver::resolve());

        return Response::success('Cart cleared.');
    }
}
