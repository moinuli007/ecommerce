<?php

namespace App\Modules\Catalog\Services;

use App\Core\DB;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Models\UnitGroup;
use RuntimeException;

/**
 * ইউনিট ও ইউনিট গ্রুপ।
 *
 * একই গ্রুপের ইউনিটগুলো একে অন্যে রূপান্তরযোগ্য। প্রতি গ্রুপে ঠিক একটাই
 * বেস ইউনিট থাকে, যার `conversion = 1`; বাকিরা বেসের সাপেক্ষে —
 * Dozen এর conversion = 12 মানে ১ ডজন = ১২ পিস।
 *
 * স্টক সবসময় **বেস ইউনিটে** জমা হয় (ফেজ ৪-এ ইনভেনটরি এলে এই নিয়মই চলবে),
 * তাই ক্রয়/বিক্রয়ে অন্য ইউনিট ব্যবহার করলে convertToBase() দিয়ে রূপান্তর করবেন।
 */
final class UnitService
{
    // -------------------------------------------------------------------------
    // গ্রুপ
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public static function saveGroup(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('গ্রুপের নাম দিতে হবে।');
        }

        $row = [
            'name'       => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'isActive'   => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        if ($id > 0) {
            UnitGroup::updateById($id, $row);

            return $id;
        }

        return UnitGroup::create($row);
    }

    public static function deleteGroup(int $id): bool
    {
        if (Unit::where('unit_group_id', $id)->exists()) {
            throw new RuntimeException('এই গ্রুপে ইউনিট আছে — আগে সেগুলো সরান।');
        }

        return UnitGroup::deleteById($id) > 0;
    }

    // -------------------------------------------------------------------------
    // ইউনিট
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $data unit_group_id, name, code, conversion, is_base?, sort_order?, isActive?
     */
    public static function saveUnit(array $data, int $id = 0): int
    {
        $name    = trim((string) ($data['name'] ?? ''));
        $code    = trim((string) ($data['code'] ?? ''));
        $groupId = (int) ($data['unit_group_id'] ?? 0);

        if ($name === '' || $code === '') {
            throw new RuntimeException('ইউনিটের নাম ও কোড দুটোই দিতে হবে।');
        }

        if (UnitGroup::find($groupId) === []) {
            throw new RuntimeException('ইউনিট গ্রুপ পাওয়া যায়নি।');
        }

        // কোড ইউনিক
        $clash = Unit::byCode($code);

        if ($clash !== [] && (int) $clash['id'] !== $id) {
            throw new RuntimeException("'$code' কোডের ইউনিট আগে থেকেই আছে।");
        }

        $isBase     = (int) ($data['is_base'] ?? 0) === 1;
        $conversion = $isBase ? 1.0 : (float) ($data['conversion'] ?? 0);

        if (!$isBase && $conversion <= 0) {
            throw new RuntimeException('রূপান্তরের হার শূন্যের বেশি হতে হবে।');
        }

        $row = [
            'unit_group_id' => $groupId,
            'name'          => $name,
            'code'          => $code,
            'conversion'    => $conversion,
            'is_base'       => $isBase ? 1 : 0,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'isActive'      => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        return DB::transaction(static function () use ($row, $id, $groupId, $isBase): int {
            if ($id > 0) {
                Unit::updateById($id, $row);
                $unitId = $id;
            } else {
                $unitId = Unit::create($row);
            }

            // প্রতি গ্রুপে ঠিক একটাই বেস — নতুনটা বেস হলে বাকিগুলোর ফ্ল্যাগ নামে
            if ($isBase) {
                DB::run(
                    'UPDATE units SET is_base = 0 WHERE unit_group_id = ? AND id <> ?',
                    [$groupId, $unitId]
                );
            }

            return $unitId;
        });
    }

    public static function deleteUnit(int $id): bool
    {
        $unit = Unit::find($id);

        if ($unit === []) {
            throw new RuntimeException('ইউনিট পাওয়া যায়নি।');
        }

        if (Product::where('unit_id', $id)->exists()) {
            throw new RuntimeException('এই ইউনিট প্রোডাক্টে ব্যবহৃত হচ্ছে — ডিলিট করা যাবে না।');
        }

        if ((int) $unit['is_base'] === 1 && Unit::ofGroup((int) $unit['unit_group_id']) !== []) {
            throw new RuntimeException('বেস ইউনিট ডিলিট করার আগে গ্রুপের অন্য ইউনিটগুলো সরান।');
        }

        return Unit::deleteById($id) > 0;
    }

    // -------------------------------------------------------------------------
    // রূপান্তর
    // -------------------------------------------------------------------------

    /** এই ইউনিটের পরিমাণ → বেস ইউনিটে */
    public static function toBase(float $quantity, int $unitId): float
    {
        $unit = Unit::find($unitId);

        if ($unit === []) {
            throw new RuntimeException('ইউনিট পাওয়া যায়নি।');
        }

        return round($quantity * (float) $unit['conversion'], 6);
    }

    /** বেস ইউনিটের পরিমাণ → এই ইউনিটে */
    public static function fromBase(float $quantity, int $unitId): float
    {
        $unit = Unit::find($unitId);

        if ($unit === [] || (float) $unit['conversion'] <= 0) {
            throw new RuntimeException('ইউনিট পাওয়া যায়নি বা রূপান্তরের হার ভুল।');
        }

        return round($quantity / (float) $unit['conversion'], 6);
    }

    /** এক ইউনিট → আরেক ইউনিট (একই গ্রুপে হতে হবে) */
    public static function convert(float $quantity, int $fromUnitId, int $toUnitId): float
    {
        $from = Unit::find($fromUnitId);
        $to   = Unit::find($toUnitId);

        if ($from === [] || $to === []) {
            throw new RuntimeException('ইউনিট পাওয়া যায়নি।');
        }

        if ((int) $from['unit_group_id'] !== (int) $to['unit_group_id']) {
            throw new RuntimeException(
                "'{$from['name']}' থেকে '{$to['name']}' এ রূপান্তর হবে না — দুটো আলাদা গ্রুপের ইউনিট।"
            );
        }

        if ((float) $to['conversion'] <= 0) {
            throw new RuntimeException('রূপান্তরের হার ভুল।');
        }

        return round($quantity * (float) $from['conversion'] / (float) $to['conversion'], 6);
    }

    /**
     * গ্রুপ অনুযায়ী সাজানো পুরো তালিকা (অ্যাডমিন পেজের জন্য)।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function grouped(): array
    {
        $groups = UnitGroup::query()->orderBy('sort_order')->orderBy('name')->get();
        $out    = [];

        foreach ($groups as $group) {
            $units = Unit::query()
                ->where('unit_group_id', (int) $group['id'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $out[] = [
                'id'         => (int) $group['id'],
                'name'       => $group['name'],
                'sort_order' => (int) $group['sort_order'],
                'isActive'   => (int) $group['isActive'],
                'units'      => array_map(
                    static fn (array $u) => [
                        'id'         => (int) $u['id'],
                        'name'       => $u['name'],
                        'code'       => $u['code'],
                        'conversion' => (float) $u['conversion'],
                        'is_base'    => (int) $u['is_base'],
                        'isActive'   => (int) $u['isActive'],
                    ],
                    $units
                ),
            ];
        }

        return $out;
    }
}
