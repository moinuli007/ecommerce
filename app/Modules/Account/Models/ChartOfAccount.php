<?php

namespace App\Modules\Account\Models;

use App\Core\Model;

final class ChartOfAccount extends Model
{
    protected static string $table = 'a_chart_of_accounts';

    /** @return array<string,mixed> */
    public static function bySystemChart(int $autoChartId): array
    {
        return static::where('system_chart', $autoChartId)->first();
    }

    /** @return array<int,array<string,mixed>> */
    public static function byMaster(int $masterAccountId): array
    {
        return static::active()->where('master_account_id', $masterAccountId)->orderBy('code')->get();
    }
}
