<?php

namespace App\Modules\Purchase\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\SupplierService;
use RuntimeException;

/**
 * সাপ্লায়ার API। অ্যাডমিন পেজ আর মোবাইল/এক্সটার্নাল ক্লায়েন্ট — দুটোই এটাই ব্যবহার করে।
 */
final class SupplierApi
{
    /** GET /api/v1/suppliers */
    public static function index(): array
    {
        return Response::success('', [
            'suppliers' => SupplierService::list([
                'q'           => Request::string('q'),
                'active_only' => Request::int('active_only'),
            ]),
        ]);
    }

    /** GET /api/v1/suppliers/{id} */
    public static function show(): array
    {
        $supplier = Supplier::find(Request::paramInt('id'));

        if ($supplier === []) {
            return Response::error('Supplier not found.');
        }

        return Response::success('', ['supplier' => $supplier]);
    }

    /** POST /api/v1/suppliers */
    public static function store(): array
    {
        // phone/code এ 'max' নিয়ম দেওয়া হয় না — Validator সংখ্যাসদৃশ স্ট্রিংকে
        // সংখ্যা ধরে তুলনা করে (length নয়); দৈর্ঘ্য SupplierService কেটে দেয়।
        if (!Validator::check(Request::all(), [
            'name'            => 'required|max:150',
            'email'           => 'email',
            'opening_balance' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            $id = SupplierService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Supplier added.', ['supplier' => Supplier::find($id)]);
    }

    /** PUT /api/v1/suppliers/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Supplier::find($id) === []) {
            return Response::error('Supplier not found.');
        }

        // phone/code এ 'max' নিয়ম দেওয়া হয় না — Validator সংখ্যাসদৃশ স্ট্রিংকে
        // সংখ্যা ধরে তুলনা করে (length নয়); দৈর্ঘ্য SupplierService কেটে দেয়।
        if (!Validator::check(Request::all(), [
            'name'            => 'required|max:150',
            'email'           => 'email',
            'opening_balance' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            SupplierService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Supplier updated.', ['supplier' => Supplier::find($id)]);
    }

    /** DELETE /api/v1/suppliers/{id} */
    public static function destroy(): array
    {
        try {
            $done = SupplierService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('Supplier deleted.')
            : Response::error('Supplier not found.');
    }
}
