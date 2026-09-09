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
use App\Modules\Purchase\Models\StockAdjustment;
use RuntimeException;

/**
 * স্টক সমন্বয় — ওপেনিং স্টক, নষ্ট/হারানো, ফিজিক্যাল গণনা-মিলকরণ।
 *
 * প্রতি লাইনের `qty` সাইনড (বাড়তি +, ঘাটতি −); Opening এ সবসময় +।
 * স্টকে বেস ইউনিটেই যায় (এখানে ইউনিট রূপান্তর নাই — সরাসরি বেস)।
 *
 * হিসাব (perpetual, doc/08-purchase.md §7):
 *   net ≥ 0 → Dr Inventory / Cr Stock Adjustment
 *   net < 0 → Dr Stock Adjustment / Cr Inventory
 *
 * Edit/Delete = reverse (স্টক + ভাউচার) তারপর re-apply।
 */
final class StockAdjustmentService
{
    /**
     * @param  array<string,mixed> $data reason, adjust_date?, note?, items[]
     * @return int adjustment id
     */
    public static function create(array $data): int
    {
        [$reason, $items, $meta] = self::validate($data);

        return DB::transaction(static function () use ($reason, $items, $meta): int {
            $header = [
                'code'        => CodeGenerator::next('stock_adjustment', 'STA'),
                'reason'      => $reason,
                'adjust_date' => $meta['adjust_date'],
                'status'      => 1,
                'note'        => $meta['note'],
            ];
            Utility::stampCreate($header);
            $adjId = DB::insert('stock_adjustments', $header);

            self::applyItems($adjId, $reason, $items, $meta['adjust_date']);

            return $adjId;
        });
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): int
    {
        $adj = StockAdjustment::find($id);

        if ($adj === []) {
            throw new RuntimeException('সমন্বয় এন্ট্রি পাওয়া যায়নি।');
        }

        [$reason, $items, $meta] = self::validate($data);

        $oldItems = StockAdjustment::itemsOf($id);
        self::assertReversible($oldItems);

        return DB::transaction(static function () use ($id, $reason, $items, $meta, $oldItems): int {
            $oldIds = array_map(static fn (array $r) => (int) $r['id'], $oldItems);

            self::reverseChangeTypes($oldIds);
            Voucher::deleteByReference(VoucherType::StockAdjustment, (string) $id);
            DB::run('DELETE FROM stock_adjustment_items WHERE adjustment_id = ?', [$id]);

            DB::update('stock_adjustments', [
                'reason'      => $reason,
                'adjust_date' => $meta['adjust_date'],
                'note'        => $meta['note'],
                'updated_at'  => time(),
            ], ['id' => $id]);

            self::applyItems($id, $reason, $items, $meta['adjust_date']);
            self::rebuildCosts(array_merge($oldItems, $items));

            return $id;
        });
    }

    public static function delete(int $id): bool
    {
        $adj = StockAdjustment::find($id);

        if ($adj === []) {
            return false;
        }

        $items = StockAdjustment::itemsOf($id);
        self::assertReversible($items);

        return DB::transaction(static function () use ($id, $items): bool {
            $ids = array_map(static fn (array $r) => (int) $r['id'], $items);

            self::reverseChangeTypes($ids);
            Voucher::deleteByReference(VoucherType::StockAdjustment, (string) $id);
            DB::run('DELETE FROM stock_adjustments WHERE id = ?', [$id]); // items cascade

            self::rebuildCosts($items);

            return true;
        });
    }

    /** @return array<string,mixed> */
    public static function details(int $id): array
    {
        $adj = StockAdjustment::find($id);

        if ($adj === []) {
            return [];
        }

        $rows = DB::select(
            "SELECT sai.*, p.name AS product_name, p.sku AS product_sku, pv.name AS variant_name
               FROM stock_adjustment_items sai
               JOIN products p ON p.id = sai.product_id
          LEFT JOIN product_variants pv ON pv.id = sai.variant_id
              WHERE sai.adjustment_id = ?
           ORDER BY sai.id",
            [$id]
        );

        return [
            'id'          => (int) $adj['id'],
            'code'        => $adj['code'],
            'reason'      => (int) $adj['reason'],
            'reason_label' => self::reasonLabel((int) $adj['reason']),
            'adjust_date' => (int) $adj['adjust_date'],
            'adjust_date_str' => (int) $adj['adjust_date'] > 0 ? date('Y-m-d', (int) $adj['adjust_date']) : '',
            'note'        => $adj['note'],
            'items'       => array_map(static fn (array $r) => [
                'id'           => (int) $r['id'],
                'product_id'   => (int) $r['product_id'],
                'variant_id'   => (int) $r['variant_id'],
                'product_name' => $r['product_name'] . ($r['variant_name'] ? ' — ' . $r['variant_name'] : ''),
                'product_sku'  => $r['product_sku'],
                'qty'          => (float) $r['qty'],
                'rate'         => (float) $r['rate'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string,mixed> $filters q?, reason?, page?, per_page?
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function list(array $filters = []): array
    {
        $build = static function () use ($filters) {
            $q = DB::table('stock_adjustments');

            if (!empty($filters['reason'])) {
                $q->where('reason', (int) $filters['reason']);
            }

            $search = trim((string) ($filters['q'] ?? ''));

            if ($search !== '') {
                $q->whereLike('code', $search);
            }

            return $q;
        };

        $total   = $build()->count();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $rows = $build()
            ->orderBy('adjust_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->page($page, min(max($perPage, 1), 200))
            ->get();

        foreach ($rows as &$row) {
            $row['reason_label'] = self::reasonLabel((int) $row['reason']);
        }
        unset($row);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return array{0:int,1:array<int,array<string,mixed>>,2:array<string,mixed>}
     */
    private static function validate(array $data): array
    {
        $reason = (int) ($data['reason'] ?? StockAdjustment::REASON_OPENING);

        if (!in_array($reason, [
            StockAdjustment::REASON_OPENING,
            StockAdjustment::REASON_DAMAGE,
            StockAdjustment::REASON_COUNT,
        ], true)) {
            throw new RuntimeException('সমন্বয়ের কারণ সঠিক নয়।');
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
            $rate      = round((float) ($line['rate'] ?? 0), 4);

            $product = Product::find($productId);

            if ($product === []) {
                throw new RuntimeException('একটা লাইনের প্রোডাক্ট পাওয়া যায়নি।');
            }

            if ($variantId > 0) {
                $variant = ProductVariant::find($variantId);

                if ($variant === [] || (int) $variant['product_id'] !== $productId) {
                    throw new RuntimeException("'{$product['name']}' এর ভ্যারিয়েন্ট মেলেনি।");
                }
            } elseif ((int) $product['has_variant'] === 1) {
                throw new RuntimeException("'{$product['name']}' ভ্যারিয়েন্ট প্রোডাক্ট — ভ্যারিয়েন্ট বাছাই করুন।");
            }

            if ($qty == 0.0) {
                throw new RuntimeException("'{$product['name']}' এর পরিমাণ শূন্য হতে পারবে না।");
            }

            if ($reason === StockAdjustment::REASON_OPENING && $qty < 0) {
                throw new RuntimeException('ওপেনিং স্টক ঋণাত্মক হতে পারবে না।');
            }

            if ($rate < 0) {
                throw new RuntimeException("'{$product['name']}' এর মূল্য ঋণাত্মক হতে পারবে না।");
            }

            $items[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty'        => $qty,
                'rate'       => $rate,
            ];
        }

        $meta = [
            'note'        => mb_substr(trim((string) ($data['note'] ?? '')), 0, 255),
            'adjust_date' => Utility::toTime((string) ($data['adjust_date'] ?? ''), time()),
        ];

        return [$reason, $items, $meta];
    }

    /**
     * লাইন বসায় — insert, stock move (signed), তারপর নেট ভাউচার।
     *
     * @param array<int,array<string,mixed>> $items
     */
    private static function applyItems(int $adjId, int $reason, array $items, int $adjustDate): void
    {
        $type = $reason === StockAdjustment::REASON_OPENING
            ? StockChangeType::Opening
            : StockChangeType::Adjust;

        $net = 0.0;

        foreach ($items as $line) {
            $itemId = DB::insert('stock_adjustment_items', [
                'adjustment_id' => $adjId,
                'product_id'    => $line['product_id'],
                'variant_id'    => $line['variant_id'],
                'qty'           => $line['qty'],
                'rate'          => $line['rate'],
            ]);

            // স্টক-ইন লাইন (Opening বা বাড়তি) weighted-avg cost এ ঢোকে — move এর আগে।
            // ঘাটতির লাইন (qty < 0) আউট-মুভমেন্ট, cost বদলায় না।
            if ($line['qty'] > 0) {
                CostService::applyPurchase(
                    $line['product_id'],
                    $line['variant_id'],
                    $line['qty'],
                    $line['rate'],
                    $itemId
                );
            }

            // qty সাইনড; Opening/Adjust দুটোরই sign() = +1, তাই যেমন আছে তেমনই বসে
            StockService::move(
                $line['product_id'],
                $line['variant_id'],
                $line['qty'],
                $type,
                $itemId,
                $adjustDate,
                $line['rate']
            );

            $net += $line['qty'] * $line['rate'];
        }

        $net = round($net, 4);

        if (abs($net) < 0.0001) {
            return; // নেট শূন্য — শুধু স্টক নড়ে, ভাউচার নয়
        }

        $inventory  = LedgerAccounts::systemLedger(AutoLedger::Inventory);
        $adjustment = LedgerAccounts::systemLedger(AutoLedger::StockAdjustment);

        [$debit, $credit] = $net >= 0 ? [$inventory, $adjustment] : [$adjustment, $inventory];

        Voucher::create(
            VoucherType::StockAdjustment,
            abs($net),
            $debit,
            $credit,
            $adjustDate,
            'Stock adjustment ' . StockAdjustment::find($adjId)['code'],
            (string) $adjId
        );
    }

    /**
     * Opening ও Adjust — দুই change_type ই মুছতে হতে পারে (reason বদলালে)।
     *
     * @param array<int,int> $itemIds
     */
    private static function reverseChangeTypes(array $itemIds): void
    {
        StockService::reverse(StockChangeType::Opening, $itemIds);
        StockService::reverse(StockChangeType::Adjust, $itemIds);
    }

    /**
     * প্রতিটা (product, variant) জোড়ার weighted-avg cost নতুন করে হিসাব।
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

    /** @param array<int,array<string,mixed>> $items */
    private static function assertReversible(array $items): void
    {
        foreach ($items as $line) {
            if ((float) $line['qty'] <= 0) {
                continue; // ঘাটতির লাইন reverse করলে স্টক বাড়ে — সমস্যা নাই
            }

            $onHand = StockService::onHand((int) $line['product_id'], (int) $line['variant_id']);

            if ($onHand - (float) $line['qty'] < -0.0001) {
                throw new RuntimeException(
                    'এই সমন্বয়ের কিছু স্টক ইতিমধ্যে ব্যবহার হয়ে গেছে — এখন এডিট/ডিলিট করলে স্টক ঋণাত্মক হবে।'
                );
            }
        }
    }

    private static function reasonLabel(int $reason): string
    {
        return match ($reason) {
            StockAdjustment::REASON_OPENING => 'Opening Stock',
            StockAdjustment::REASON_DAMAGE  => 'Damage / Loss',
            StockAdjustment::REASON_COUNT   => 'Count Correction',
            default                         => 'Adjustment',
        };
    }
}
