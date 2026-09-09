<?php

namespace App\Modules\Purchase\Services;

use App\Core\DB;
use App\Core\Utility;
use App\Enum\AutoLedger;
use App\Enum\StockChangeType;
use App\Enum\VoucherType;
use App\Modules\Account\Services\CodeGenerator;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Services\UnitService;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\Supplier;
use RuntimeException;

/**
 * ক্রয় ফেরত। একটা ক্রয় রেফার করে; পণ্য সাপ্লায়ারকে ফেরত যায়।
 *
 * প্রতি লাইনে StockService::move(PurchaseReturn) — sign() = −1, তাই স্টক কমে।
 * ভাউচার (perpetual, doc §7): Dr Supplier / Cr Inventory।
 * Cost (`purchase_price`) ছোঁয়া হয় না — ফেরত আউট-মুভমেন্ট, weighted-avg বদলায় না।
 */
final class PurchaseReturnService
{
    /**
     * @param  array<string,mixed> $data purchase_id, return_date?, note?, items[]
     * @return int return id
     */
    public static function create(array $data): int
    {
        [$purchase, $supplier, $items, $meta] = self::validate($data);

        return DB::transaction(static function () use ($purchase, $supplier, $items, $meta): int {
            $header = [
                'code'        => CodeGenerator::next('purchase_return', 'PRN'),
                'purchase_id' => (int) $purchase['id'],
                'supplier_id' => (int) $supplier['id'],
                'return_date' => $meta['return_date'],
                'sub_total'   => 0,
                'status'      => 1,
                'note'        => $meta['note'],
            ];
            Utility::stampCreate($header);
            $returnId = DB::insert('purchase_returns', $header);

            $subTotal = 0.0;

            foreach ($items as $line) {
                $qtyBase   = UnitService::toBase($line['qty'], $line['unit_id']);
                $lineTotal = round($line['qty'] * $line['unit_price'], 4);
                $rateBase  = $qtyBase > 0 ? round($lineTotal / $qtyBase, 4) : $line['unit_price'];

                $itemId = DB::insert('purchase_return_items', [
                    'return_id'   => $returnId,
                    'product_id'  => $line['product_id'],
                    'variant_id'  => $line['variant_id'],
                    'unit_id'     => $line['unit_id'],
                    'qty'         => $line['qty'],
                    'qty_base'    => $qtyBase,
                    'unit_price'  => $line['unit_price'],
                    'line_total'  => $lineTotal,
                ]);

                StockService::move(
                    $line['product_id'],
                    $line['variant_id'],
                    $qtyBase, // magnitude — sign() = −1 প্রয়োগ হবে
                    StockChangeType::PurchaseReturn,
                    $itemId,
                    $meta['return_date'],
                    $rateBase
                );

                $subTotal += $lineTotal;
            }

            $subTotal = round($subTotal, 4);
            DB::update('purchase_returns', ['sub_total' => $subTotal, 'updated_at' => time()], ['id' => $returnId]);

            if ($subTotal > 0) {
                Voucher::create(
                    VoucherType::PurchaseReturn,
                    $subTotal,
                    LedgerAccounts::supplierLedger($supplier),           // Dr — পাওনা কমল
                    LedgerAccounts::systemLedger(AutoLedger::Inventory), // Cr — ইনভেন্টরি কমল
                    $meta['return_date'],
                    'Purchase return ' . $header['code'],
                    (string) $returnId
                );
            }

            return $returnId;
        });
    }

    public static function delete(int $id): bool
    {
        $return = PurchaseReturn::find($id);

        if ($return === []) {
            return false;
        }

        return DB::transaction(static function () use ($id): bool {
            $ids = array_map(
                static fn (array $r) => (int) $r['id'],
                PurchaseReturn::itemsOf($id)
            );

            StockService::reverse(StockChangeType::PurchaseReturn, $ids);
            Voucher::deleteByReference(VoucherType::PurchaseReturn, (string) $id);
            DB::run('DELETE FROM purchase_returns WHERE id = ?', [$id]); // items cascade

            return true;
        });
    }

    /** @return array<string,mixed> */
    public static function details(int $id): array
    {
        $return = PurchaseReturn::find($id);

        if ($return === []) {
            return [];
        }

        $supplier = Supplier::find((int) $return['supplier_id']);
        $purchase = Purchase::find((int) $return['purchase_id']);

        $rows = DB::select(
            "SELECT pri.*, p.name AS product_name, p.sku AS product_sku,
                    pv.name AS variant_name, u.code AS unit_code
               FROM purchase_return_items pri
               JOIN products p ON p.id = pri.product_id
          LEFT JOIN product_variants pv ON pv.id = pri.variant_id
               JOIN units u ON u.id = pri.unit_id
              WHERE pri.return_id = ?
           ORDER BY pri.id",
            [$id]
        );

        return [
            'id'            => (int) $return['id'],
            'code'          => $return['code'],
            'purchase_id'   => (int) $return['purchase_id'],
            'purchase_code' => $purchase['code'] ?? '',
            'supplier_name' => $supplier['name'] ?? '',
            'return_date'   => (int) $return['return_date'],
            'return_date_str' => (int) $return['return_date'] > 0 ? date('Y-m-d', (int) $return['return_date']) : '',
            'sub_total'     => (float) $return['sub_total'],
            'note'          => $return['note'],
            'items'         => array_map(static fn (array $r) => [
                'id'           => (int) $r['id'],
                'product_name' => $r['product_name'] . ($r['variant_name'] ? ' — ' . $r['variant_name'] : ''),
                'product_sku'  => $r['product_sku'],
                'unit_code'    => $r['unit_code'],
                'qty'          => (float) $r['qty'],
                'unit_price'   => (float) $r['unit_price'],
                'line_total'   => (float) $r['line_total'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string,mixed> $filters q?, page?, per_page?
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function list(array $filters = []): array
    {
        $build = static function () use ($filters) {
            $q = DB::table('purchase_returns')
                ->select('purchase_returns.*', 'suppliers.name AS supplier_name')
                ->leftJoin('suppliers', 'suppliers.id = purchase_returns.supplier_id');

            $search = trim((string) ($filters['q'] ?? ''));

            if ($search !== '') {
                $q->whereRaw('purchase_returns.code LIKE ?', ["%$search%"]);
            }

            return $q;
        };

        $total   = $build()->count();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $rows = $build()
            ->orderBy('purchase_returns.return_date', 'DESC')
            ->orderBy('purchase_returns.id', 'DESC')
            ->page($page, min(max($perPage, 1), 200))
            ->get();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<int,array<string,mixed>>,3:array<string,mixed>}
     */
    private static function validate(array $data): array
    {
        $purchase = Purchase::find((int) ($data['purchase_id'] ?? 0));

        if ($purchase === []) {
            throw new RuntimeException('কোন ক্রয় থেকে ফেরত — সেই ক্রয় বাছাই করুন।');
        }

        $supplier = Supplier::find((int) $purchase['supplier_id']);

        if ($supplier === []) {
            throw new RuntimeException('সাপ্লায়ার পাওয়া যায়নি।');
        }

        // ওই ক্রয়ের লাইনগুলো — কী কী ফেরত দেওয়া যায়
        $purchased = DB::select(
            'SELECT product_id, variant_id, unit_id, SUM(qty) AS qty FROM purchase_items
              WHERE purchase_id = ? GROUP BY product_id, variant_id, unit_id',
            [(int) $purchase['id']]
        );

        $allowed = [];

        foreach ($purchased as $row) {
            $allowed[$row['product_id'] . ':' . $row['variant_id']] = [
                'unit_id' => (int) $row['unit_id'],
                'qty'     => (float) $row['qty'],
            ];
        }

        // ইতিমধ্যে ফেরত
        $returned = DB::select(
            'SELECT pri.product_id, pri.variant_id, SUM(pri.qty) AS qty
               FROM purchase_return_items pri
               JOIN purchase_returns pr ON pr.id = pri.return_id
              WHERE pr.purchase_id = ?
           GROUP BY pri.product_id, pri.variant_id',
            [(int) $purchase['id']]
        );

        $already = [];

        foreach ($returned as $row) {
            $already[$row['product_id'] . ':' . $row['variant_id']] = (float) $row['qty'];
        }

        $rawItems = $data['items'] ?? [];

        if (!is_array($rawItems) || $rawItems === []) {
            throw new RuntimeException('অন্তত একটা লাইন যোগ করুন।');
        }

        $items = [];

        foreach ($rawItems as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $variantId = (int) ($line['variant_id'] ?? 0);
            $qty       = round((float) ($line['qty'] ?? 0), 4);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 4);
            $key       = $productId . ':' . $variantId;

            if (!isset($allowed[$key])) {
                throw new RuntimeException('এই ক্রয়ে ছিল না এমন পণ্য ফেরত দেওয়া যাবে না।');
            }

            if ($qty <= 0) {
                throw new RuntimeException('ফেরতের পরিমাণ শূন্যের বেশি হতে হবে।');
            }

            $remaining = $allowed[$key]['qty'] - ($already[$key] ?? 0);

            if ($qty - $remaining > 0.0001) {
                throw new RuntimeException(
                    'ফেরতের পরিমাণ কেনা পরিমাণের বেশি হতে পারবে না (বাকি: ' . $remaining . ')।'
                );
            }

            $product = Product::find($productId);

            if ($product === []) {
                throw new RuntimeException('প্রোডাক্ট পাওয়া যায়নি।');
            }

            if ($variantId > 0 && ProductVariant::find($variantId) === []) {
                throw new RuntimeException('ভ্যারিয়েন্ট পাওয়া যায়নি।');
            }

            $unitId = $allowed[$key]['unit_id'];

            if (Unit::find($unitId) === []) {
                throw new RuntimeException('ইউনিট পাওয়া যায়নি।');
            }

            // ফেরত দেওয়ার মতো স্টক আছে তো?
            $onHand = StockService::onHand($productId, $variantId);
            $qtyBase = UnitService::toBase($qty, $unitId);

            if ($onHand - $qtyBase < -0.0001) {
                throw new RuntimeException("'{$product['name']}' এর যথেষ্ট স্টক নাই ফেরত দেওয়ার মতো।");
            }

            $items[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'unit_id'    => $unitId,
                'qty'        => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        $meta = [
            'note'        => mb_substr(trim((string) ($data['note'] ?? '')), 0, 255),
            'return_date' => Utility::toTime((string) ($data['return_date'] ?? ''), time()),
        ];

        return [$purchase, $supplier, $items, $meta];
    }
}
