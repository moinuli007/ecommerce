<?php

namespace App\Modules\Account\Services;

use App\Core\DB;
use App\Core\RequestTime;

/**
 * অটো কোড জেনারেটর — `code_sequence` টেবিল থেকে নাম্বার নেয়।
 *
 *     CodeGenerator::next('voucher', 'SL');   // SL-000123
 *     CodeGenerator::next('ledger_entry');    // LE-00000451
 *
 * নাম্বারটা `SELECT ... FOR UPDATE` দিয়ে লক করে নেওয়া হয়, তাই একসাথে অনেক
 * রিকোয়েস্ট এলেও ডুপ্লিকেট কোড হবে না।
 */
final class CodeGenerator
{
    public static function next(string $name, string $prefix = ''): string
    {
        return DB::transaction(static function () use ($name, $prefix): string {
            $row = DB::selectOne(
                'SELECT * FROM code_sequence WHERE name = ? LIMIT 1 FOR UPDATE',
                [$name]
            );

            if ($row === []) {
                DB::insert('code_sequence', [
                    'name'        => $name,
                    'prefix'      => $prefix,
                    'next_number' => 1,
                    'padding'     => 6,
                    'created_at'  => RequestTime::now(),
                    'updated_at'  => RequestTime::now(),
                ]);

                $row = DB::selectOne(
                    'SELECT * FROM code_sequence WHERE name = ? LIMIT 1 FOR UPDATE',
                    [$name]
                );
            }

            $number  = (int) $row['next_number'];
            $padding = (int) $row['padding'];
            $usePrefix = $prefix !== '' ? $prefix : (string) $row['prefix'];

            DB::update(
                'code_sequence',
                ['next_number' => $number + 1, 'updated_at' => RequestTime::now()],
                ['id' => (int) $row['id']]
            );

            $serial = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            return $usePrefix === '' ? $serial : $usePrefix . '-' . $serial;
        });
    }

    /**
     * একসাথে অনেকগুলো কোড (কম্পাউন্ড ভাউচারের সব লাইনের জন্য এক কলে)।
     *
     * @return array<int,string>
     */
    public static function nextMany(string $name, int $count, string $prefix = ''): array
    {
        if ($count <= 0) {
            return [];
        }

        return DB::transaction(static function () use ($name, $count, $prefix): array {
            $row = DB::selectOne(
                'SELECT * FROM code_sequence WHERE name = ? LIMIT 1 FOR UPDATE',
                [$name]
            );

            if ($row === []) {
                DB::insert('code_sequence', [
                    'name'        => $name,
                    'prefix'      => $prefix,
                    'next_number' => 1,
                    'padding'     => 6,
                    'created_at'  => RequestTime::now(),
                    'updated_at'  => RequestTime::now(),
                ]);

                $row = DB::selectOne(
                    'SELECT * FROM code_sequence WHERE name = ? LIMIT 1 FOR UPDATE',
                    [$name]
                );
            }

            $start     = (int) $row['next_number'];
            $padding   = (int) $row['padding'];
            $usePrefix = $prefix !== '' ? $prefix : (string) $row['prefix'];

            DB::update(
                'code_sequence',
                ['next_number' => $start + $count, 'updated_at' => RequestTime::now()],
                ['id' => (int) $row['id']]
            );

            $codes = [];

            for ($i = 0; $i < $count; $i++) {
                $serial  = str_pad((string) ($start + $i), $padding, '0', STR_PAD_LEFT);
                $codes[] = $usePrefix === '' ? $serial : $usePrefix . '-' . $serial;
            }

            return $codes;
        });
    }
}
