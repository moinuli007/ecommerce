<?php

namespace App\Modules\Sale\Services;

use App\Core\DB;
use App\Modules\Sale\Models\DeliveryZone;
use RuntimeException;

/**
 * ডেলিভারি জোন CRUD। doc/10-storefront-order.md §২।
 */
final class DeliveryZoneService
{
    /**
     * নতুন জোন অথবা এডিট।
     *
     * @param  array<string,mixed> $data name, fee, free_delivery_threshold?, is_default?, sort_order?, isActive?
     * @return int জোন id
     */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Zone name is required.');
        }

        $fee = round((float) ($data['fee'] ?? 0), 4);

        if ($fee < 0) {
            throw new RuntimeException('Delivery fee cannot be negative.');
        }

        $threshold = round((float) ($data['free_delivery_threshold'] ?? 0), 4);

        if ($threshold < 0) {
            throw new RuntimeException('Free delivery threshold cannot be negative.');
        }

        $isDefault = (int) ($data['is_default'] ?? 0) === 1;

        $row = [
            'name'                     => mb_substr($name, 0, 100),
            'fee'                      => $fee,
            'free_delivery_threshold' => $threshold,
            'is_default'               => $isDefault ? 1 : 0,
            'sort_order'               => (int) ($data['sort_order'] ?? 0),
            'isActive'                 => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        return DB::transaction(static function () use ($row, $id, $isDefault): int {
            if ($id > 0) {
                DeliveryZone::updateById($id, $row);
                $zoneId = $id;
            } else {
                $zoneId = DeliveryZone::create($row);
            }

            // ঠিক একটাই ডিফল্ট জোন থাকবে — নতুনটা ডিফল্ট হলে বাকিগুলোর ফ্ল্যাগ নামে
            // (UnitService এর is_base এর একই প্যাটার্ন)
            if ($isDefault) {
                DB::run('UPDATE delivery_zones SET is_default = 0 WHERE id <> ?', [$zoneId]);
            }

            return $zoneId;
        });
    }

    /**
     * ডিলিট। কোনো অর্ডার এই জোন ব্যবহার করলে আটকে দেয় — নীরবে হিসাব/রসিদ
     * ভাঙার চেয়ে স্পষ্ট এরর ভালো।
     */
    public static function delete(int $id): bool
    {
        if (DeliveryZone::find($id) === []) {
            return false;
        }

        if (DB::table('orders')->where('delivery_zone_id', $id)->exists()) {
            throw new RuntimeException('This zone has orders — it cannot be deleted.');
        }

        return DeliveryZone::deleteById($id) > 0;
    }

    /**
     * অ্যাডমিন লিস্ট / স্টোরফ্রন্ট চেকআউট ড্রপডাউনের জন্য।
     *
     * @param  array<string,mixed> $filters active_only?
     * @return array<int,array<string,mixed>>
     */
    public static function list(array $filters = []): array
    {
        $query = DeliveryZone::query()->orderBy('sort_order')->orderBy('name');

        if (($filters['active_only'] ?? 0) == 1) {
            $query->active();
        }

        return $query->get();
    }
}
