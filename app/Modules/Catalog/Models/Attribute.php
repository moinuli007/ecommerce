<?php

namespace App\Modules\Catalog\Models;

use App\Core\Model;

final class Attribute extends Model
{
    protected static string $table = 'attributes';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }
}
