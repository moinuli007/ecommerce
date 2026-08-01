<?php

namespace App\Modules\Account\Models;

use App\Core\Model;
use App\Enum\VoucherType;

final class VoucherEntry extends Model
{
    protected static string $table = 'a_voucher_entry';

    /** @return array<string,mixed> */
    public static function byCode(string $code): array
    {
        return static::where('code', $code)->first();
    }

    /**
     * টাইপ + সোর্স ডকুমেন্ট ধরে ভাউচার খোঁজা।
     * যেমন: একটা অর্ডারের Sale ভাউচার আগে থেকে আছে কি না।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function byTypeAndReference(VoucherType $type, string $reference): array
    {
        return static::query()
            ->where('type', $type->value)
            ->where('reference', $reference)
            ->orderBy('id')
            ->get();
    }
}
