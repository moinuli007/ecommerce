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
            return Response::error('Category not found.');
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

        return Response::success('Category added.', ['category' => Category::find($id)]);
    }

    /** PUT /api/v1/categories/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Category::find($id) === []) {
            return Response::error('Category not found.');
        }

        if (!Validator::check(Request::all(), ['name' => 'required|max:150'])) {
            return Response::payload();
        }

        try {
            CategoryService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Category updated.', ['category' => Category::find($id)]);
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
            ? Response::success('Category deleted.')
            : Response::error('Category not found.');
    }

    /** POST /api/v1/categories/{id}/image — multipart, field image */
    public static function uploadImage(): array
    {
        $file = Request::file('image');

        if ($file === []) {
            return Response::error('Please choose an image.');
        }

        try {
            $path = CategoryService::uploadImage(Request::paramInt('id'), $file);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Image uploaded.', ['image' => $path]);
    }

    /** DELETE /api/v1/categories/{id}/image */
    public static function removeImage(): array
    {
        CategoryService::removeImage(Request::paramInt('id'));

        return Response::success('Image removed.');
    }
}
