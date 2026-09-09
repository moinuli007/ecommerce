<?php

namespace App\Modules\Catalog\Services;

use App\Core\DB;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use RuntimeException;

/**
 * ভ্যারিয়েন্ট অ্যাট্রিবিউট — Size (S/M/L/XL/XXL), Color, ইত্যাদি।
 *
 * `code` কোড থেকে রেফার করার জন্য (`size`, `color`) — একবার সেট হলে বদলাবেন না,
 * কারণ SKU তে ভ্যালুর কোড বসে যায়।
 *
 * ⚠ যে ভ্যালু কোনো ভ্যারিয়েন্টে ব্যবহৃত হয়েছে সেটা ডিলিট করা যাবে না —
 * তাহলে পুরোনো অর্ডারে "কোন সাইজ ছিল" সেটা হারিয়ে যাবে। বদলে isActive = 0।
 */
final class AttributeService
{
    // -------------------------------------------------------------------------
    // অ্যাট্রিবিউট
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $data name, code, type?, sort_order?, isActive? */
    public static function save(array $data, int $id = 0): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Attribute name is required.');
        }

        if ($code === '') {
            $code = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name) ?? '');
        }

        $clash = Attribute::byCode($code);

        if ($clash !== [] && (int) $clash['id'] !== $id) {
            throw new RuntimeException("An attribute with code '$code' already exists.");
        }

        $type = (string) ($data['type'] ?? 'select');

        if (!in_array($type, ['select', 'color'], true)) {
            throw new RuntimeException('Attribute type must be select or color.');
        }

        $row = [
            'name'       => $name,
            'code'       => $code,
            'type'       => $type,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'isActive'   => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        if ($id > 0) {
            Attribute::updateById($id, $row);

            return $id;
        }

        return Attribute::create($row);
    }

    public static function delete(int $id): bool
    {
        if (self::valueInUse(0, $id)) {
            throw new RuntimeException('This attribute is used by a product variant — it cannot be deleted.');
        }

        return Attribute::deleteById($id) > 0;
    }

    // -------------------------------------------------------------------------
    // ভ্যালু
    // -------------------------------------------------------------------------

    /** @param array<string,mixed> $data attribute_id, value, code?, color_hex?, sort_order?, isActive? */
    public static function saveValue(array $data, int $id = 0): int
    {
        $attributeId = (int) ($data['attribute_id'] ?? 0);
        $value       = trim((string) ($data['value'] ?? ''));

        $attribute = Attribute::find($attributeId);

        if ($attribute === []) {
            throw new RuntimeException('Attribute not found.');
        }

        if ($value === '') {
            throw new RuntimeException('Value is required.');
        }

        $code = trim((string) ($data['code'] ?? '')) ?: strtoupper(
            preg_replace('/[^a-zA-Z0-9]+/', '', $value) ?? ''
        );

        if ($code === '') {
            $code = 'V' . time();
        }

        $clash = AttributeValue::query()
            ->where('attribute_id', $attributeId)
            ->where('code', $code)
            ->first();

        if ($clash !== [] && (int) $clash['id'] !== $id) {
            throw new RuntimeException("A value with code '$code' already exists for this attribute.");
        }

        $row = [
            'attribute_id' => $attributeId,
            'value'        => $value,
            'code'         => $code,
            'color_hex'    => (string) ($data['color_hex'] ?? ''),
            'sort_order'   => (int) ($data['sort_order'] ?? 0),
            'isActive'     => (int) ($data['isActive'] ?? 1) === 1 ? 1 : 0,
        ];

        if ($id > 0) {
            AttributeValue::updateById($id, $row);

            return $id;
        }

        return AttributeValue::create($row);
    }

    public static function deleteValue(int $id): bool
    {
        if (self::valueInUse($id)) {
            throw new RuntimeException(
                'This value is used by a product variant or image — deactivate it instead of deleting.'
            );
        }

        return AttributeValue::deleteById($id) > 0;
    }

    // -------------------------------------------------------------------------
    // পড়া
    // -------------------------------------------------------------------------

    /**
     * সব অ্যাট্রিবিউট, প্রতিটির ভ্যালু সহ (ভ্যারিয়েন্ট বিল্ডারে লাগে)।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function withValues(bool $activeOnly = true): array
    {
        $query = Attribute::query();

        if ($activeOnly) {
            $query->active();
        }

        $attributes = $query->orderBy('sort_order')->orderBy('id')->get();
        $out        = [];

        foreach ($attributes as $attribute) {
            $out[] = [
                'id'         => (int) $attribute['id'],
                'name'       => $attribute['name'],
                'code'       => $attribute['code'],
                'type'       => $attribute['type'],
                'sort_order' => (int) $attribute['sort_order'],
                'isActive'   => (int) $attribute['isActive'],
                'values'     => array_map(
                    static fn (array $v) => [
                        'id'         => (int) $v['id'],
                        'value'      => $v['value'],
                        'code'       => $v['code'],
                        'color_hex'  => $v['color_hex'],
                        'sort_order' => (int) $v['sort_order'],
                        'isActive'   => (int) $v['isActive'],
                    ],
                    AttributeValue::ofAttribute((int) $attribute['id'], $activeOnly)
                ),
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    /**
     * ভ্যালু (বা পুরো অ্যাট্রিবিউট) কোনো ভ্যারিয়েন্টে ব্যবহৃত হচ্ছে কি না।
     */
    private static function valueInUse(int $valueId, int $attributeId = 0): bool
    {
        if ($attributeId > 0) {
            return (int) DB::scalar(
                'SELECT COUNT(*) FROM product_variant_values WHERE attribute_id = ?',
                [$attributeId],
                0
            ) > 0;
        }

        $inVariant = (int) DB::scalar(
            'SELECT COUNT(*) FROM product_variant_values WHERE attribute_value_id = ?',
            [$valueId],
            0
        ) > 0;

        $inImage = (int) DB::scalar(
            'SELECT COUNT(*) FROM product_images WHERE attribute_value_id = ?',
            [$valueId],
            0
        ) > 0;

        return $inVariant || $inImage;
    }
}
