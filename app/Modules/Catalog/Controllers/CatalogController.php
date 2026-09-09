<?php

namespace App\Modules\Catalog\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Catalog\Api\AttributeApi;
use App\Modules\Catalog\Api\CategoryApi;
use App\Modules\Catalog\Api\ProductApi;
use App\Modules\Catalog\Api\UnitApi;

/**
 * ক্যাটালগের সব অ্যাডমিন পেজ।
 *
 * বাকি কন্ট্রোলারের মতোই — কোনো DB/সার্ভিস কল নাই, ডেটা শুধু Api ক্লাস থেকে
 * (সিদ্ধান্ত D-04)। পেজে সেভ/ডিলিট JS থেকে সরাসরি API এ যায়।
 */
final class CatalogController
{
    // -------------------------------------------------------------------------
    // ক্যাটাগরি
    // -------------------------------------------------------------------------

    public static function categories(): string
    {
        Response::reset();

        $payload = CategoryApi::index();

        return View::layout('layouts/admin', 'Catalog::category/index', [
            'title'    => 'Categories',
            'tree'     => $payload['tree'] ?? [],
            'options'  => $payload['options'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    // -------------------------------------------------------------------------
    // ইউনিট
    // -------------------------------------------------------------------------

    public static function units(): string
    {
        Response::reset();

        $payload = UnitApi::index();

        return View::layout('layouts/admin', 'Catalog::unit/index', [
            'title'    => 'Units',
            'groups'   => $payload['groups'] ?? [],
            'messages' => $payload['m'] ?? [],
        ]);
    }

    // -------------------------------------------------------------------------
    // অ্যাট্রিবিউট (Size, Color)
    // -------------------------------------------------------------------------

    public static function attributes(): string
    {
        Response::reset();

        $payload = AttributeApi::index();

        return View::layout('layouts/admin', 'Catalog::attribute/index', [
            'title'      => 'Size & Other Attributes',
            'attributes' => $payload['attributes'] ?? [],
            'messages'   => $payload['m'] ?? [],
        ]);
    }

    // -------------------------------------------------------------------------
    // প্রোডাক্ট
    // -------------------------------------------------------------------------

    public static function products(): string
    {
        Response::reset();
        $payload = ProductApi::index();

        Response::reset();
        $form = ProductApi::formData();

        return View::layout('layouts/admin', 'Catalog::product/list', [
            'title'      => 'Products',
            'products'   => $payload['products'] ?? [],
            'pagination' => $payload['pagination'] ?? [],
            'categories' => $form['categories'] ?? [],
            'filters'    => [
                'q'           => Request::string('q'),
                'category_id' => Request::string('category_id'),
                'isActive'    => Request::string('isActive'),
            ],
            'messages'   => $payload['m'] ?? [],
        ]);
    }

    /** নতুন প্রোডাক্টের ফর্ম */
    public static function productCreate(): string
    {
        return self::productForm(0);
    }

    /** এডিট ফর্ম — /admin/products/{id}/edit */
    public static function productEdit(): string
    {
        return self::productForm(Request::paramInt('id'));
    }

    private static function productForm(int $productId): string
    {
        Response::reset();
        $form = ProductApi::formData();

        $product = [];

        if ($productId > 0) {
            Response::reset();
            Request::setParams(['id' => (string) $productId]);
            $product = ProductApi::show()['product'] ?? [];
        }

        return View::layout('layouts/admin', 'Catalog::product/form', [
            'title'      => $productId > 0 ? 'Edit Product' : 'New Product',
            'product'    => $product,
            'categories' => $form['categories'] ?? [],
            'units'      => $form['units'] ?? [],
            'attributes' => $form['attributes'] ?? [],
            'messages'   => $form['m'] ?? [],
        ]);
    }
}
