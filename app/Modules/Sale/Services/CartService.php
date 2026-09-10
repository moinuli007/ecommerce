<?php

namespace App\Modules\Sale\Services;

use App\Core\DB;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Sale\Models\Cart;
use App\Modules\Sale\Models\CartItem;
use RuntimeException;

/**
 * কার্ট — গেস্ট-friendly। কোনো মেথডই `$_COOKIE`/`$_SERVER` ছোঁয় না, টোকেন সবসময়
 * প্যারামিটার হিসেবে আসে — কুকি পড়া/বসানো `CartApi`-র কাজ (doc/01-architecture.md
 * এর লেয়ারিং)।
 *
 * দাম কখনো `cart_items`-এ জমা থাকে না — `details()` প্রতিবার লাইভ
 * `ProductService::effectivePrice()` দিয়ে হিসাব করে। doc/10-storefront-order.md §৪।
 */
final class CartService
{
    /**
     * টোকেন দিয়ে কার্ট খোঁজে, না পেলে নতুন বানায় (নতুন র‍্যান্ডম টোকেনসহ —
     * ক্লায়েন্টের পাঠানো অচেনা টোকেন ব্যবহার হয় না, নতুন টোকেনই আসল সত্য)।
     *
     * @return array<string,mixed>
     */
    public static function resolveCart(string $incomingToken): array
    {
        if ($incomingToken !== '') {
            $cart = Cart::byToken($incomingToken);

            if ($cart !== []) {
                return $cart;
            }
        }

        $token = bin2hex(random_bytes(32));
        $id    = Cart::create(['token' => $token, 'customer_id' => 0]);

        return Cart::find($id);
    }

    /**
     * পুরো কার্ট, লাইভ দাম সহ — স্টোরফ্রন্টের কার্ট ভিউ/ড্রয়ারের জন্য।
     *
     * @return array<string,mixed> token, items, sub_total, count
     */
    public static function details(string $token): array
    {
        $cart  = self::resolveCart($token);
        $items = CartItem::ofCart((int) $cart['id']);

        if ($items === []) {
            return ['token' => $cart['token'], 'items' => [], 'sub_total' => 0.0, 'count' => 0];
        }

        $productIds = array_values(array_unique(array_map(static fn (array $i) => (int) $i['product_id'], $items)));
        $products   = array_column(Product::findMany($productIds), null, 'id');
        $images     = ProductImage::primaryOf($productIds);

        $variantIds = array_values(array_filter(array_map(static fn (array $i) => (int) $i['variant_id'], $items)));
        $variants   = $variantIds === [] ? [] : array_column(ProductVariant::findMany($variantIds), null, 'id');

        $lines    = [];
        $subTotal = 0.0;

        foreach ($items as $item) {
            $product = $products[(int) $item['product_id']] ?? [];

            // প্রোডাক্ট মুছে গেলে/নিষ্ক্রিয় হলে — লাইনটা চুপচাপ বাদ (checkout এ আবার যাচাই হবে)
            if ($product === [] || (int) $product['isActive'] !== 1) {
                continue;
            }

            $variantId = (int) $item['variant_id'];
            $variant   = $variantId > 0 ? ($variants[$variantId] ?? []) : [];

            if ($variantId > 0 && ($variant === [] || (int) $variant['isActive'] !== 1)) {
                continue;
            }

            $price     = ProductService::effectivePrice($product, $variant);
            $qty       = (float) $item['qty'];
            $lineTotal = round($price['price'] * $qty, 4);
            $subTotal += $lineTotal;

            $lines[] = [
                'id'               => (int) $item['id'],
                'product_id'       => (int) $product['id'],
                'product_slug'     => $product['slug'],
                'variant_id'       => $variantId,
                'name'             => $product['name'] . (($variant['name'] ?? '') !== '' ? ' — ' . $variant['name'] : ''),
                'image'            => $images[(int) $product['id']] ?? '',
                'unit_price'       => $price['price'],
                'regular_price'    => $price['regular'],
                'on_offer'         => $price['on_offer'],
                'qty'              => $qty,
                'line_total'       => round($lineTotal, 2),
            ];
        }

        return [
            'token'     => $cart['token'],
            'items'     => $lines,
            'sub_total' => round($subTotal, 2),
            'count'     => count($lines),
        ];
    }

    /**
     * যোগ করা / qty বাড়ানো — একই লাইন (product + variant) থাকলে merge।
     *
     * @return array<string,mixed> কার্ট ডিটেইল (details() এর শেপ)
     */
    public static function addItem(string $token, int $productId, int $variantId, float $qty): array
    {
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        $product = Product::find($productId);

        if ($product === [] || (int) $product['isActive'] !== 1) {
            throw new RuntimeException('Product not found.');
        }

        if ((int) $product['has_variant'] === 1) {
            if ($variantId <= 0) {
                throw new RuntimeException('Please select a size/color.');
            }

            $variant = ProductVariant::find($variantId);

            if ($variant === [] || (int) $variant['product_id'] !== $productId || (int) $variant['isActive'] !== 1) {
                throw new RuntimeException('Variant not found.');
            }
        } else {
            $variantId = 0;
        }

        $cart  = self::resolveCart($token);
        $cartId = (int) $cart['id'];

        DB::transaction(static function () use ($cartId, $productId, $variantId, $qty): void {
            $existing = CartItem::line($cartId, $productId, $variantId);

            if ($existing !== []) {
                CartItem::updateById((int) $existing['id'], ['qty' => (float) $existing['qty'] + $qty]);
            } else {
                CartItem::create([
                    'cart_id'    => $cartId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'qty'        => $qty,
                ]);
            }
        });

        return self::details((string) $cart['token']);
    }

    /**
     * qty বদল — `0` বা তার নিচে দিলে লাইন ডিলিট।
     *
     * @return array<string,mixed>
     */
    public static function updateItemQty(string $token, int $itemId, float $qty): array
    {
        $cart = self::resolveCart($token);
        $item = CartItem::find($itemId);

        if ($item === [] || (int) $item['cart_id'] !== (int) $cart['id']) {
            throw new RuntimeException('Cart item not found.');
        }

        if ($qty <= 0) {
            CartItem::deleteById($itemId);
        } else {
            CartItem::updateById($itemId, ['qty' => $qty]);
        }

        return self::details((string) $cart['token']);
    }

    /** @return array<string,mixed> */
    public static function removeItem(string $token, int $itemId): array
    {
        return self::updateItemQty($token, $itemId, 0);
    }

    public static function clear(string $token): void
    {
        $cart = self::resolveCart($token);

        DB::table('cart_items')->where('cart_id', (int) $cart['id'])->delete();
    }
}
