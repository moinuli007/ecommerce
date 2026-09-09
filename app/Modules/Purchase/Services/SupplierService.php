<?php

namespace App\Modules\Purchase\Services;

use App\Core\DB;
use App\Enum\TransactionType;
use App\Enum\VoucherType;
use App\Modules\Account\Services\CodeGenerator;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Purchase\Models\Supplier;
use RuntimeException;

/**
 * সাপ্লায়ার CRUD + ওপেনিং ব্যালেন্স।
 *
 * ওপেনিং ব্যালেন্স = ইনস্টলের সময় সাপ্লায়ারের কাছে আমাদের যা বকেয়া। এটা
 * `OpeningSupplier` ভাউচারে পোস্ট হয় (Dr Opening Balance / Cr সাপ্লায়ার লেজার)।
 * অ্যামাউন্ট বদলালে পুরনো ভাউচার মুছে নতুন করে পোস্ট হয় — তাই idempotent।
 *
 * doc/08-purchase.md §2, §13 (ধাপ ২)।
 */
final class SupplierService
{
    /**
     * নতুন সাপ্লায়ার অথবা এডিট।
     *
     * @param  array<string,mixed> $data name, phone?, email?, address?, opening_balance?, isActive?
     * @return int সাপ্লায়ার id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('সাপ্লায়ারের নাম দিতে হবে।');
        }

        $newOpening = round((float) ($data['opening_balance'] ?? 0), 4);

        if ($newOpening < 0) {
            throw new RuntimeException('ওপেনিং ব্যালেন্স ঋণাত্মক হতে পারবে না।');
        }

        $oldOpening = $id > 0
            ? round((float) (Supplier::find($id)['opening_balance'] ?? 0), 4)
            : 0.0;

        $row = [
            'name'            => mb_substr($name, 0, 150),
            'phone'           => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 30),
            'email'           => mb_substr(trim((string) ($data['email'] ?? '')), 0, 150),
            'address'         => mb_substr(trim((string) ($data['address'] ?? '')), 0, 255),
            'opening_balance' => $newOpening,
            'isActive'        => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        return DB::transaction(static function () use ($row, $id, $newOpening, $oldOpening): int {
            if ($id > 0) {
                Supplier::updateById($id, $row);
                $supplierId = $id;
            } else {
                $row['code'] = CodeGenerator::next('supplier', 'SUP');
                $supplierId  = Supplier::create($row);
            }

            if ($newOpening !== $oldOpening) {
                Voucher::deleteByReference(VoucherType::OpeningSupplier, (string) $supplierId);

                if ($newOpening > 0) {
                    $supplier = Supplier::find($supplierId);
                    $ledgerId = LedgerAccounts::supplierLedger($supplier);

                    Voucher::opening(
                        VoucherType::OpeningSupplier,
                        $ledgerId,
                        $newOpening,
                        TransactionType::Credit, // সাপ্লায়ারকে আমরা দেব
                        time(),
                        (string) $supplierId,
                        'Opening balance'
                    );
                }
            }

            return $supplierId;
        });
    }

    /**
     * ডিলিট। কোনো ক্রয় বা ক্রয়-ফেরত থাকলে আটকে দেয় — নীরবে হিসাব ভাঙার চেয়ে
     * স্পষ্ট এরর ভালো।
     */
    public static function delete(int $id): bool
    {
        if (Supplier::find($id) === []) {
            return false;
        }

        if (DB::table('purchases')->where('supplier_id', $id)->exists()) {
            throw new RuntimeException('এই সাপ্লায়ারের ক্রয় এন্ট্রি আছে — ডিলিট করা যাবে না।');
        }

        if (DB::table('purchase_returns')->where('supplier_id', $id)->exists()) {
            throw new RuntimeException('এই সাপ্লায়ারের ক্রয়-ফেরত এন্ট্রি আছে — ডিলিট করা যাবে না।');
        }

        return DB::transaction(static function () use ($id): bool {
            Voucher::deleteByReference(VoucherType::OpeningSupplier, (string) $id);

            return Supplier::deleteById($id) > 0;
        });
    }

    /**
     * অ্যাডমিন লিস্ট / ড্রপডাউনের জন্য।
     *
     * @param  array<string,mixed> $filters q?, active_only?
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = []): array
    {
        $query = Supplier::query()->orderBy('name');

        if (($filters['active_only'] ?? 0) == 1) {
            $query->active();
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->whereRaw('(name LIKE ? OR phone LIKE ? OR code LIKE ?)', [
                "%$search%", "%$search%", "%$search%",
            ]);
        }

        return $query->get();
    }
}
