<?php

namespace App\Modules\Account\Models;

use App\Core\Model;

final class MasterAccount extends Model
{
    protected static string $table = 'a_master_account';

    /** @return array<string,mixed> */
    public static function bySystemRef(int $autoMasterAccountId): array
    {
        return static::where('system_ref', $autoMasterAccountId)->first();
    }
}
