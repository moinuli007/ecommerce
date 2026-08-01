<?php

namespace App\Modules\Catalog\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryService;
use RuntimeException;

/**
 * ক্যাটাগরি API। রেফারেন্স সাইটের "Collections" মেনু এই গাছ থেকেই আসবে।
 */
final class CategoryApi
{
    /** GET /api/v1/categories — পুরো গাছ */
    public static function index(): array
    {
        return Response::success('', [
            'tree'     => CategoryService::tree(Request::int('active_only') === 1),
            'options'  => CategoryService::options(),
            'featured' => CategoryService::featured(),
        ]);
    }

    /** GET /api/v1/categories/{id} */
    public static function show(): array
    {
        $category = Category::find(Request::paramInt('id'));

        if ($category === []) {
            return Response::error('ক্যাটাগরি পাওয়া যায়নি।');
        }

        return Response::success('', [
            'category'   => $category,
            'breadcrumb' => CategoryService::breadcrumb((int) $category['id']),
            'children'   => Category::children((int) $category['id'], false),
        ]);
    }

    /** POST /api/v1/categories */
    public static function store(): array
    {
        if (!Validator::check(Request::all(), ['name' => 'required|max:150'])) {
            return Response::payload();
        }

        try {
            $id = CategoryService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ক্যাটাগরি যোগ হয়েছে।', ['category' => Category::find($id)]);
    }

    /** PUT /api/v1/categories/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Category::find($id) === []) {
            return Response::error('ক্যাটাগরি পাওয়া যায়নি।');
        }

        if (!Validator::check(Request::all(), ['name' => 'required|max:150'])) {
            return Response::payload();
        }

        try {
            CategoryService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('ক্যাটাগরি আপডেট হয়েছে।', ['category' => Category::find($id)]);
    }

    /** DELETE /api/v1/categories/{id} */
    public static function destroy(): array
    {
        try {
            $done = CategoryService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('ক্যাটাগরি ডিলিট হয়েছে।')
            : Response::error('ক্যাটাগরি পাওয়া যায়নি।');
    }
}
