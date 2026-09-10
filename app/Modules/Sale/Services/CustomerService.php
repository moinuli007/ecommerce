<?php

namespace App\Modules\Sale\Services;

use App\Core\DB;
use App\Enum\TransactionType;
use App\Enum\VoucherType;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Sale\Models\Customer;
use RuntimeException;

/**
 * কাস্টমার CRUD + ওপেনিং ব্যালেন্স + গেস্ট-চেকআউট ডিডুপ।
 *
 * ওপেনিং ব্যালেন্স = কাস্টমারের কাছে আমাদের যা পাওনা (`OpeningCustomer`
 * ভাউচার, Dr কাস্টমার লেজার / Cr Opening) — `SupplierService::save()` এর
 * আয়না-প্রতিরূপ, শুধু `TransactionType::Debit` ("আমরা পাব")।
 *
 * doc/10-storefront-order.md §৩।
 */
final class CustomerService
{
    /**
     * নতুন কাস্টমার অথবা এডিট (অ্যাডমিন CRUD)।
     *
     * @param  array<string,mixed> $data name, phone, email?, opening_balance?, isActive?
     * @return int কাস্টমার id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Customer name is required.');
        }

        $phone = trim((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('Phone number is required.');
        }

        $clash = Customer::byPhone($phone);

        if ($clash !== [] && (int) $clash['id'] !== $id) {
            throw new RuntimeException("A customer with phone '$phone' already exists.");
        }

        $newOpening = round((float) ($data['opening_balance'] ?? 0), 4);

        if ($newOpening < 0) {
            throw new RuntimeException('Opening balance cannot be negative.');
        }

        $oldOpening = $id > 0
            ? round((float) (Customer::find($id)['opening_balance'] ?? 0), 4)
            : 0.0;

        $row = [
            'name'            => mb_substr($name, 0, 150),
            'phone'           => mb_substr($phone, 0, 20),
            'email'           => mb_substr(trim((string) ($data['email'] ?? '')), 0, 150),
            'opening_balance' => $newOpening,
            'isActive'        => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        return DB::transaction(static function () use ($row, $id, $newOpening, $oldOpening): int {
            if ($id > 0) {
                Customer::updateById($id, $row);
                $customerId = $id;
            } else {
                $customerId = Customer::create($row);
            }

            if ($newOpening !== $oldOpening) {
                Voucher::deleteByReference(VoucherType::OpeningCustomer, (string) $customerId);

                if ($newOpening > 0) {
                    $customer = Customer::find($customerId);
                    $ledgerId = LedgerAccounts::customerLedger($customer);

                    Voucher::opening(
                        VoucherType::OpeningCustomer,
                        $ledgerId,
                        $newOpening,
                        TransactionType::Debit, // কাস্টমারের কাছে আমরা পাব
                        time(),
                        (string) $customerId,
                        'Opening balance'
                    );
                }
            }

            return $customerId;
        });
    }

    /**
     * চেকআউটের গেস্ট-ডিডুপ — `phone` দিয়ে খুঁজে পেলে সেই রো ফেরত, না পেলে নতুন
     * বানায়। বিদ্যমান কাস্টমারের নাম/ইমেইল এখানে ওভাররাইট হয় না — কারও
     * প্রোফাইল আরেকজনের গেস্ট চেকআউটে ভুল করে বদলে যাওয়া ঠেকাতে।
     *
     * @param  array<string,mixed> $data phone, name, email?, user_id?
     * @return array<string,mixed>
     */
    public static function findOrCreateByPhone(array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            throw new RuntimeException('Phone number is required.');
        }

        $existing = Customer::byPhone($phone);

        if ($existing !== []) {
            return $existing;
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        $id = Customer::create([
            'user_id'         => (int) ($data['user_id'] ?? 0),
            'name'            => mb_substr($name, 0, 150),
            'phone'           => mb_substr($phone, 0, 20),
            'email'           => mb_substr(trim((string) ($data['email'] ?? '')), 0, 150),
            'opening_balance' => 0,
            'isActive'        => 1,
        ]);

        return Customer::find($id);
    }

    /**
     * ডিলিট। কোনো অর্ডার থাকলে আটকে দেয় — নীরবে ইতিহাস ভাঙার চেয়ে স্পষ্ট এরর ভালো।
     */
    public static function delete(int $id): bool
    {
        if (Customer::find($id) === []) {
            return false;
        }

        if (DB::table('orders')->where('customer_id', $id)->exists()) {
            throw new RuntimeException('This customer has orders — it cannot be deleted.');
        }

        return DB::transaction(static function () use ($id): bool {
            Voucher::deleteByReference(VoucherType::OpeningCustomer, (string) $id);

            return Customer::deleteById($id) > 0;
        });
    }

    /**
     * অ্যাডমিন লিস্টের জন্য।
     *
     * @param  array<string,mixed> $filters q?, active_only?
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = []): array
    {
        $query = Customer::query()->orderBy('name');

        if (($filters['active_only'] ?? 0) == 1) {
            $query->active();
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->whereRaw('(name LIKE ? OR phone LIKE ? OR email LIKE ?)', [
                "%$search%", "%$search%", "%$search%",
            ]);
        }

        return $query->get();
    }
}
