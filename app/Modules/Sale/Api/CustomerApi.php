<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Account\Services\LedgerStatement;
use App\Modules\Sale\Models\Customer;
use App\Modules\Sale\Services\CustomerService;
use RuntimeException;

/**
 * কাস্টমার API। অ্যাডমিন CRUD পেজ এটাই ব্যবহার করে; পরে OrderService::checkout()
 * (doc §৬) সরাসরি CustomerService::findOrCreateByPhone() কল করবে, এই Api ক্লাস না —
 * গেস্ট চেকআউট কোনো guard-সুরক্ষিত এন্ডপয়েন্টের ওপর নির্ভর করবে না।
 */
final class CustomerApi
{
    /** GET /api/v1/customers */
    public static function index(): array
    {
        return Response::success('', [
            'customers' => CustomerService::list([
                'q'           => Request::string('q'),
                'active_only' => Request::int('active_only'),
            ]),
        ]);
    }

    /** GET /api/v1/customers/{id} — প্রোফাইল + লেজার ব্যালেন্স */
    public static function show(): array
    {
        $customer = Customer::find(Request::paramInt('id'));

        if ($customer === []) {
            return Response::error('Customer not found.');
        }

        $ledgerId = (int) $customer['ledger_id'];

        return Response::success('', [
            'customer'       => $customer,
            'ledger_balance' => $ledgerId > 0 ? LedgerStatement::balance($ledgerId) : ['balance' => 0.0, 'side' => ''],
            // অর্ডার হিস্টোরি — Order মডিউল (doc/10-storefront-order.md §৫) বিল্ড হলে যোগ হবে
            'orders'         => [],
        ]);
    }

    /** POST /api/v1/customers */
    public static function store(): array
    {
        // phone এ 'max' নিয়ম দেওয়া হয় না — Validator সংখ্যাসদৃশ স্ট্রিংকে
        // সংখ্যা ধরে তুলনা করে (length নয়); দৈর্ঘ্য CustomerService কেটে দেয়
        // (SupplierApi এর একই গোচা, doc/08-purchase.md এর কনভেনশন)।
        if (!Validator::check(Request::all(), [
            'name'            => 'required|max:150',
            'phone'           => 'required',
            'email'           => 'email',
            'opening_balance' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            $id = CustomerService::save(Request::all());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Customer added.', ['customer' => Customer::find($id)]);
    }

    /** PUT /api/v1/customers/{id} */
    public static function update(): array
    {
        $id = Request::paramInt('id');

        if (Customer::find($id) === []) {
            return Response::error('Customer not found.');
        }

        // phone এ 'max' নিয়ম দেওয়া হয় না — Validator সংখ্যাসদৃশ স্ট্রিংকে
        // সংখ্যা ধরে তুলনা করে (length নয়); দৈর্ঘ্য CustomerService কেটে দেয়
        // (SupplierApi এর একই গোচা, doc/08-purchase.md এর কনভেনশন)।
        if (!Validator::check(Request::all(), [
            'name'            => 'required|max:150',
            'phone'           => 'required',
            'email'           => 'email',
            'opening_balance' => 'numeric|min:0',
        ])) {
            return Response::payload();
        }

        try {
            CustomerService::save(Request::all(), $id);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Customer updated.', ['customer' => Customer::find($id)]);
    }

    /** DELETE /api/v1/customers/{id} */
    public static function destroy(): array
    {
        try {
            $done = CustomerService::delete(Request::paramInt('id'));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return $done
            ? Response::success('Customer deleted.')
            : Response::error('Customer not found.');
    }
}
