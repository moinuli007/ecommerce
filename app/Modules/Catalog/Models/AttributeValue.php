<?php

namespace App\Modules\Catalog\Models;

use App\Core\Model;

final class AttributeValue extends Model
{
    protected static string $table = 'attribute_values';

    /** @return array<int,array<string,mixed>> */
    public static function ofAttribute(int $attributeId, bool $activeOnly = true): array
    {
        $query = static::where('attribute_id', $attributeId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }
}
