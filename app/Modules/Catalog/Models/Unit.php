<?php

namespace App\Modules\Catalog\Models;

use App\Core\DB;
use App\Core\Model;

final class Unit extends Model
{
    protected static string $table = 'units';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }

    /** @return array<string,mixed> ওই গ্রুপের বেস ইউনিট */
    public static function baseOf(int $unitGroupId): array
    {
        return static::query()
            ->where('unit_group_id', $unitGroupId)
            ->where('is_base', 1)
            ->first();
    }

    /** @return array<int,array<string,mixed>> */
    public static function ofGroup(int $unitGroupId): array
    {
        return static::active()
            ->where('unit_group_id', $unitGroupId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * ড্রপডাউনের জন্য — গ্রুপের নাম সহ।
     *
     * @return array<int,array{id:int,name:string,code:string,group:string}>
     */
    public static function options(): array
    {
        $rows = DB::select(
            'SELECT u.id, u.name, u.code, g.name AS group_name
               FROM units u
               JOIN unit_groups g ON g.id = u.unit_group_id
              WHERE u.isActive = 1 AND g.isActive = 1
           ORDER BY g.sort_order, u.sort_order'
        );

        return array_map(
            static fn (array $r) => [
                'id'    => (int) $r['id'],
                'name'  => $r['name'],
                'code'  => $r['code'],
                'group' => $r['group_name'],
            ],
            $rows
        );
    }
}
