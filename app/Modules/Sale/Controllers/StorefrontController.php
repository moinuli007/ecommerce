<?php

namespace App\Modules\Sale\Controllers;

use App\Core\Auth;
use App\Core\Message;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Catalog\Api\CategoryApi;
use App\Modules\Catalog\Api\ProductApi;
use App\Modules\Sale\Api\CartApi;
use App\Modules\Sale\Api\DeliveryZoneApi;

/**
 * স্টোরফ্রন্ট — হোম, কালেকশন/PDP, কার্ট, চেকআউট, অর্ডার-ট্র্যাকিং।
 *
 * বাকি কন্ট্রোলারের মতোই — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। এই কন্ট্রোলার একাধিক মডিউলের Api কল করে (Catalog + Sale)
 * কারণ পাবলিক সাইট কোনো একটা মডিউলের সীমানায় আটকে থাকে না।
 *
 * doc/10-storefront-order.md §০, §৬।
 */
final class StorefrontController
{
    public static function home(): string
    {
        $newArrivals = ProductApi::section(['isNew' => 1, 'sort' => 'newest', 'per_page' => 8]);
        $bestsellers = ProductApi::section(['isFeatured' => 1, 'per_page' => 8]);

        $common = self::common();

        return View::layout('layouts/storefront', 'Sale::storefront/home', array_merge($common, [
            'title'       => '',
            'collections' => $common['navCategories'],
            'featured'    => $common['navFeatured'],
            'newArrivals' => $newArrivals,
            'bestsellers' => $bestsellers,
        ]));
    }

    /** `/collections/{slug}` — `slug = "all"` মানে সব প্রোডাক্ট, ক্যাটাগরি ফিল্টার ছাড়া */
    public static function collection(): string
    {
        $slug = (string) Request::param('slug', '');

        $category = [];

        if ($slug !== '' && $slug !== 'all') {
            Response::reset();
            $categoryPayload = CategoryApi::publicShow();

            if (($categoryPayload['status'] ?? 0) !== 1) {
                Response::reset();

                return View::layout('layouts/storefront', 'Sale::storefront/not-found', array_merge(self::common(), [
                    'title'   => 'Not found',
                    'message' => 'This collection does not exist.',
                ]));
            }

            $category = $categoryPayload['category'];
        }

        $sort = Request::string('sort');
        $page = Request::int('page', 1);

        Response::reset();
        $result = ProductApi::publicListResponse([
            'category' => $slug !== 'all' ? $slug : '',
            'sort'     => $sort,
            'page'     => $page,
            'per_page' => 24,
        ]);

        return View::layout('layouts/storefront', 'Sale::storefront/collection', array_merge(self::common(), [
            'title'      => $category['name'] ?? 'All Products',
            'category'   => $category,
            'products'   => $result['products'] ?? [],
            'pagination' => $result['pagination'] ?? ['total' => 0, 'page' => 1, 'per_page' => 24, 'pages' => 1],
            'sort'       => $sort,
            'slug'       => $slug,
        ]));
    }

    public static function product(): string
    {
        Response::reset();
        $payload = ProductApi::publicShowBySlug();

        if (($payload['status'] ?? 0) !== 1) {
            Response::reset();

            return View::layout('layouts/storefront', 'Sale::storefront/not-found', array_merge(self::common(), [
                'title'   => 'Not found',
                'message' => 'This product is not available.',
            ]));
        }

        return View::layout('layouts/storefront', 'Sale::storefront/product', array_merge(self::common(), [
            'title'   => $payload['product']['name'],
            'product' => $payload['product'],
        ]));
    }

    public static function cart(): string
    {
        Response::reset();
        $cart = CartApi::show();

        return View::layout('layouts/storefront', 'Sale::storefront/cart', array_merge(self::common(), [
            'title' => 'Your Cart',
            'items'    => $cart['items'] ?? [],
            'subTotal' => $cart['sub_total'] ?? 0,
        ]));
    }

    public static function checkout(): string
    {
        Response::reset();
        $zones = DeliveryZoneApi::publicIndex();

        Response::reset();
        $cart = CartApi::show();

        return View::layout('layouts/storefront', 'Sale::storefront/checkout', array_merge(self::common(), [
            'title'    => 'Checkout',
            'zones'    => $zones['zones'] ?? [],
            'items'    => $cart['items'] ?? [],
            'subTotal' => $cart['sub_total'] ?? 0,
        ]));
    }

    public static function trackOrder(): string
    {
        return View::layout('layouts/storefront', 'Sale::storefront/track', array_merge(self::common(), [
            'title' => 'Track Your Order',
        ]));
    }

    /**
     * সব পেজে লাগে — নেভ ক্যাটাগরি, কার্ট কাউন্ট, লগইন কাস্টমার (নেভের
     * Account লিংক এখান থেকেই রেন্ডার হয়, doc/11-customer-account.md §৮)।
     * `AccountController` ও এটা পুনর্ব্যবহার করে বলে public।
     *
     * @return array<string,mixed>
     */
    public static function common(): array
    {
        // সেশন-ফ্ল্যাশ করা মেসেজ (রিডাইরেক্টের পরে দেখানোর জন্য, যেমন লগআউট)
        // এখানেই ধরে ফেলা লাগে — নিচের Api কলগুলো নিজেরাই Response::payload()
        // এর ভেতর Message::flush() করে, তাই পরে ডাকলে ততক্ষণে কিউ খালি পেত।
        $messages = Message::flush();

        Response::reset();
        $tree = CategoryApi::publicTree();

        Response::reset();
        $cart = CartApi::show();

        return [
            'navCategories' => $tree['tree'] ?? [],
            'navFeatured'   => $tree['featured'] ?? [],
            'cartCount'     => (int) ($cart['count'] ?? 0),
            'authCustomer'  => Auth::isCustomer() ? Auth::user() : [],
            'messages'      => $messages,
        ];
    }
}
