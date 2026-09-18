<?php

namespace App\Modules\Purchase\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\RequestTime;
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
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\Supplier;
use RuntimeException;

/**
 * ক্রয় এন্ট্রি — সাপ্লায়ার থেকে পণ্য কেনা, স্টক বাড়ানো, হিসাব পোস্ট করা।
 *
 * পুরোটা এক ট্রানজেকশনে (doc/08-purchase.md §6):
 *   1. ভ্যালিডেশন
 *   2. purchases + purchase_items
 *   3. প্রতি লাইনে — unit→base, CostService (weighted-avg), StockService::move
 *   4. হেডারে sub_total / total
 *   5. Voucher::create(Purchase) — Dr Inventory / Cr Supplier
 *
 * Edit/Delete = reverse (stock + voucher) তারপর re-apply; cost rebuild।
 */
final class PurchaseService
{
    // -------------------------------------------------------------------------
    // তৈরি
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data supplier_id, invoice_date?, supplier_invoice_no?,
     *                             challan_no?, po_no?, discount?, note?, items[]
     * @return int purchase id
     */
    public static function create(array $data): int
    {
        [$supplier, $items, $meta] = self::validate($data);

        return DB::transaction(static function () use ($supplier, $items, $meta): int {
            $header = [
                'code'                => CodeGenerator::next('purchase', 'PU'),
                'supplier_id'         => (int) $supplier['id'],
                'supplier_invoice_no' => $meta['supplier_invoice_no'],
                'invoice_date'        => $meta['invoice_date'],
                'challan_no'          => $meta['challan_no'],
                'po_no'               => $meta['po_no'],
                'sub_total'           => 0,
                'discount'            => $meta['discount'],
                'total'               => 0,
                'status'              => 1,
                'note'                => $meta['note'],
            ];
            Utility::stampCreate($header);
            $purchaseId = DB::insert('purchases', $header);

            $subTotal = self::applyItems($purchaseId, $items, $meta['invoice_date']);
            $total    = round($subTotal - $meta['discount'], 4);

            DB::update('purchases', [
                'sub_total'  => $subTotal,
                'total'      => $total,
                'updated_at' => RequestTime::now(),
            ], ['id' => $purchaseId]);

            self::postVoucher($purchaseId, $supplier, $total, $meta['invoice_date'], $header['code']);

            return $purchaseId;
        });
    }

    // -------------------------------------------------------------------------
    // এডিট — reverse তারপর re-apply
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data create() এর মতোই শেপ
     */
    public static function update(int $id, array $data): int
    {
        $purchase = Purchase::find($id);

        if ($purchase === []) {
            throw new RuntimeException('Purchase entry not found.');
        }

        [$supplier, $items, $meta] = self::validate($data);

        $oldItems = PurchaseItem::ofPurchase($id);
        $oldItemIds = array_map(static fn (array $r) => (int) $r['id'], $oldItems);

        self::assertReversible($oldItems);

        return DB::transaction(static function () use ($id, $supplier, $items, $meta, $oldItems, $oldItemIds): int {
            // reverse — স্টক, ভাউচার, পুরনো লাইন
            StockService::reverse(StockChangeType::Purchase, $oldItemIds);
            Voucher::deleteByReference(VoucherType::Purchase, (string) $id);
            DB::run('DELETE FROM purchase_items WHERE purchase_id = ?', [$id]);

            DB::update('purchases', [
                'supplier_id'         => (int) $supplier['id'],
                'supplier_invoice_no' => $meta['supplier_invoice_no'],
                'invoice_date'        => $meta['invoice_date'],
                'challan_no'          => $meta['challan_no'],
                'po_no'               => $meta['po_no'],
                'discount'            => $meta['discount'],
                'note'                => $meta['note'],
                'updated_at'          => RequestTime::now(),
                'updated_by'          => Auth::id(),
            ], ['id' => $id]);

            $subTotal = self::applyItems($id, $items, $meta['invoice_date']);
            $total    = round($subTotal - $meta['discount'], 4);

            DB::update('purchases', ['sub_total' => $subTotal, 'total' => $total], ['id' => $id]);

            self::postVoucher($id, $supplier, $total, $meta['invoice_date'], (string) Purchase::find($id)['code']);

            // পুরনো + নতুন — দুই সেটের প্রতিটা (product, variant) এর cost নতুন করে
            self::rebuildCosts(array_merge($oldItems, self::itemsForRebuild($items)));

            return $id;
        });
    }

    // -------------------------------------------------------------------------
    // ডিলিট
    // -------------------------------------------------------------------------

    public static function delete(int $id): bool
    {
        $purchase = Purchase::find($id);

        if ($purchase === []) {
            return false;
        }

        $items = PurchaseItem::ofPurchase($id);
        self::assertReversible($items);

        return DB::transaction(static function () use ($id, $items): bool {
            $itemIds = array_map(static fn (array $r) => (int) $r['id'], $items);

            StockService::reverse(StockChangeType::Purchase, $itemIds);
            Voucher::deleteByReference(VoucherType::Purchase, (string) $id);
            DB::run('DELETE FROM purchases WHERE id = ?', [$id]); // items cascade

            self::rebuildCosts($items);

            return true;
        });
    }

    // -------------------------------------------------------------------------
    // পড়া
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> না পেলে খালি array */
    public static function details(int $id): array
    {
        $purchase = Purchase::find($id);

        if ($purchase === []) {
            return [];
        }

        $supplier = Supplier::find((int) $purchase['supplier_id']);

        $rows = DB::select(
            "SELECT pi.*, p.name AS product_name, p.sku AS product_sku,
                    pv.name AS variant_name, u.name AS unit_name, u.code AS unit_code
               FROM purchase_items pi
               JOIN products p        ON p.id = pi.product_id
          LEFT JOIN product_variants pv ON pv.id = pi.variant_id
               JOIN units u           ON u.id = pi.unit_id
              WHERE pi.purchase_id = ?
           ORDER BY pi.id",
            [$id]
        );

        return [
            'id'                  => (int) $purchase['id'],
            'code'                => $purchase['code'],
            'supplier_id'         => (int) $purchase['supplier_id'],
            'supplier_name'       => $supplier['name'] ?? '',
            'supplier_invoice_no' => $purchase['supplier_invoice_no'],
            'invoice_date'        => (int) $purchase['invoice_date'],
            'invoice_date_str'    => (int) $purchase['invoice_date'] > 0
                ? date('Y-m-d', (int) $purchase['invoice_date']) : '',
            'challan_no'          => $purchase['challan_no'],
            'po_no'               => $purchase['po_no'],
            'sub_total'           => (float) $purchase['sub_total'],
            'discount'            => (float) $purchase['discount'],
            'total'               => (float) $purchase['total'],
            'note'                => $purchase['note'],
            'items'               => array_map(static fn (array $r) => [
                'id'           => (int) $r['id'],
                'product_id'   => (int) $r['product_id'],
                'variant_id'   => (int) $r['variant_id'],
                'product_name' => $r['product_name']
                    . ($r['variant_name'] ? ' — ' . $r['variant_name'] : ''),
                'product_sku'  => $r['product_sku'],
                'unit_id'      => (int) $r['unit_id'],
                'unit_name'    => $r['unit_name'],
                'unit_code'    => $r['unit_code'],
                'qty'          => (float) $r['qty'],
                'qty_base'     => (float) $r['qty_base'],
                'unit_price'   => (float) $r['unit_price'],
                'sale_price'   => (float) $r['sale_price'],
                'line_total'   => (float) $r['line_total'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string,mixed> $filters q?, supplier_id?, page?, per_page?
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function list(array $filters = []): array
    {
        $build = static function () use ($filters) {
            $q = DB::table('purchases')->select(
                'purchases.*',
                'suppliers.name AS supplier_name'
            )->leftJoin('suppliers', 'suppliers.id = purchases.supplier_id');

            if (!empty($filters['supplier_id'])) {
                $q->where('purchases.supplier_id', (int) $filters['supplier_id']);
            }

            $search = trim((string) ($filters['q'] ?? ''));

            if ($search !== '') {
                $q->whereRaw('(purchases.code LIKE ? OR purchases.supplier_invoice_no LIKE ?)', [
                    "%$search%", "%$search%",
                ]);
            }

            return $q;
        };

        $total   = $build()->count();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $rows = $build()
            ->orderBy('purchases.invoice_date', 'DESC')
            ->orderBy('purchases.id', 'DESC')
            ->page($page, min(max($perPage, 1), 200))
            ->get();

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>,2:array<string,mixed>}
     */
    private static function validate(array $data): array
    {
        $supplier = Supplier::find((int) ($data['supplier_id'] ?? 0));

        if ($supplier === []) {
            throw new RuntimeException('Please choose a supplier.');
        }

        if ((int) $supplier['isActive'] !== 1) {
            throw new RuntimeException('This supplier is inactive.');
        }

        $rawItems = $data['items'] ?? [];

        if (!is_array($rawItems) || $rawItems === []) {
            throw new RuntimeException('Add at least one line.');
        }

        $items = [];

        foreach ($rawItems as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $variantId = (int) ($line['variant_id'] ?? 0);
            $unitId    = (int) ($line['unit_id'] ?? 0);
            $qty       = round((float) ($line['qty'] ?? 0), 4);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 4);
            $salePrice = round((float) ($line['sale_price'] ?? 0), 4);

            $product = Product::find($productId);

            if ($product === []) {
                throw new RuntimeException('A line\'s product was not found.');
            }

            if ($variantId > 0) {
                $variant = ProductVariant::find($variantId);

                if ($variant === [] || (int) $variant['product_id'] !== $productId) {
                    throw new RuntimeException("'{$product['name']}' variant does not match.");
                }
            } elseif ((int) $product['has_variant'] === 1) {
                throw new RuntimeException("'{$product['name']}' is a variant product — please choose a variant.");
            }

            if (Unit::find($unitId) === []) {
                throw new RuntimeException("Please choose a unit for '{$product['name']}'.");
            }

            if ($qty <= 0) {
                throw new RuntimeException("Quantity for '{$product['name']}' must be greater than zero.");
            }

            if ($unitPrice < 0) {
                throw new RuntimeException("Price for '{$product['name']}' cannot be negative.");
            }

            if ($salePrice < 0) {
                throw new RuntimeException("Sale price for '{$product['name']}' cannot be negative.");
            }

            $items[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'unit_id'    => $unitId,
                'qty'        => $qty,
                'unit_price' => $unitPrice,
                'sale_price' => $salePrice,
            ];
        }

        $discount = round((float) ($data['discount'] ?? 0), 4);

        if ($discount < 0) {
            throw new RuntimeException('Discount cannot be negative.');
        }

        $meta = [
            'supplier_invoice_no' => mb_substr(trim((string) ($data['supplier_invoice_no'] ?? '')), 0, 60),
            'challan_no'          => mb_substr(trim((string) ($data['challan_no'] ?? '')), 0, 60),
            'po_no'               => mb_substr(trim((string) ($data['po_no'] ?? '')), 0, 60),
            'note'                => mb_substr(trim((string) ($data['note'] ?? '')), 0, 255),
            'discount'            => $discount,
            'invoice_date'       => Utility::toTime((string) ($data['invoice_date'] ?? ''), RequestTime::now()),
        ];

        return [$supplier, $items, $meta];
    }

    /**
     * লাইনগুলো বসায় — insert, cost (move এর আগে), stock move। sub_total ফেরত।
     *
     * @param array<int,array<string,mixed>> $items
     */
    private static function applyItems(int $purchaseId, array $items, int $invoiceDate): float
    {
        $subTotal = 0.0;

        foreach ($items as $line) {
            $qtyBase   = UnitService::toBase($line['qty'], $line['unit_id']);
            $lineTotal = round($line['qty'] * $line['unit_price'], 4);
            $rateBase  = $qtyBase > 0 ? round($lineTotal / $qtyBase, 4) : $line['unit_price'];

            $itemId = DB::insert('purchase_items', [
                'purchase_id' => $purchaseId,
                'product_id'  => $line['product_id'],
                'variant_id'  => $line['variant_id'],
                'unit_id'     => $line['unit_id'],
                'qty'         => $line['qty'],
                'qty_base'    => $qtyBase,
                'unit_price'  => $line['unit_price'],
                'sale_price'  => $line['sale_price'],
                'line_total'  => $lineTotal,
            ]);

            // cost আগে (old stock লাগে), তারপর stock move
            CostService::applyPurchase($line['product_id'], $line['variant_id'], $qtyBase, $rateBase, $itemId);

            StockService::move(
                $line['product_id'],
                $line['variant_id'],
                $qtyBase,
                StockChangeType::Purchase,
                $itemId,
                $invoiceDate,
                $rateBase
            );

            // বিক্রয় মূল্য — দিলে সরাসরি ওভাররাইট (weighted-average না, costing না —
            // doc/09-media-and-purchase-pricing.md সিদ্ধান্ত P-07)। সবসময় প্রোডাক্ট-লেভেলে
            // বসে (variant_id নির্বিশেষে) — একই প্রোডাক্টের সব রঙ/সাইজ এক দামে বিক্রি হয়,
            // ভ্যারিয়েন্টের নিজস্ব sale_price নাই।
            if ($line['sale_price'] > 0) {
                Product::updateById($line['product_id'], ['sale_price' => $line['sale_price']]);
            }

            $subTotal += $lineTotal;
        }

        return round($subTotal, 4);
    }

    /**
     * @param array<string,mixed> $supplier by-ref নয় — কপি; supplierLedger() ledger_id বসায়
     */
    private static function postVoucher(
        int $purchaseId,
        array $supplier,
        float $total,
        int $invoiceDate,
        string $code
    ): void {
        if ($total <= 0) {
            return; // zero-value / 100% discount — শুধু স্টক, ভাউচার নয়
        }

        $supplierLedger = LedgerAccounts::supplierLedger($supplier);

        Voucher::create(
            VoucherType::Purchase,
            $total,
            LedgerAccounts::systemLedger(AutoLedger::Inventory), // Dr
            $supplierLedger,                                     // Cr — সাপ্লায়ারের পাওনা বাড়ল
            $invoiceDate,
            'Purchase ' . $code,
            (string) $purchaseId
        );
    }

    /**
     * প্রতিটা (product, variant) জোড়ার cost নতুন করে হিসাব করে।
     *
     * @param array<int,array<string,mixed>> $items
     */
    private static function rebuildCosts(array $items): void
    {
        $seen = [];

        foreach ($items as $line) {
            $key = (int) $line['product_id'] . ':' . (int) $line['variant_id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            CostService::rebuild((int) $line['product_id'], (int) $line['variant_id']);
        }
    }

    /**
     * rebuildCosts() এর জন্য নতুন items কে {product_id, variant_id} শেপে আনে।
     *
     * @param  array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private static function itemsForRebuild(array $items): array
    {
        return array_map(static fn (array $l) => [
            'product_id' => (int) $l['product_id'],
            'variant_id' => (int) $l['variant_id'],
        ], $items);
    }

    /**
     * reverse করলে কোনো আইটেমের স্টক ঋণাত্মক হয়ে যাবে কি না — গেলে আটকাই।
     *
     * @param array<int,array<string,mixed>> $items
     */
    private static function assertReversible(array $items): void
    {
        foreach ($items as $line) {
            $onHand = StockService::onHand((int) $line['product_id'], (int) $line['variant_id']);

            if ($onHand - (float) $line['qty_base'] < -0.0001) {
                throw new RuntimeException(
                    'Some of this purchase\'s products have already been sold/used — '
                    . 'editing/deleting now would make the stock negative.'
                );
            }
        }
    }
}
