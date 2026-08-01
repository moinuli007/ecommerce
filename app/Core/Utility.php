<?php

namespace App\Core;

/**
 * ছোটখাটো হেল্পার — erp_saas এর app/Methods/Utility.php এর সমতুল্য।
 */
final class Utility
{
    /**
     * created_at/created_by অথবা updated_at/updated_by বসিয়ে দেয়।
     * প্রতিটা insert/update এর আগে এটা কল করার নিয়ম।
     *
     * @param array<string,mixed> $data
     */
    public static function stampCreate(array &$data): void
    {
        $now  = time();
        $user = Auth::id();

        $data['created_at'] = $data['created_at'] ?? $now;
        $data['created_by'] = $data['created_by'] ?? $user;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['updated_by'] = $data['updated_by'] ?? $user;
    }

    /** @param array<string,mixed> $data */
    public static function stampUpdate(array &$data): void
    {
        $data['updated_at'] = time();
        $data['updated_by'] = Auth::id();
    }

    /**
     * লিস্টকে id-কি ওয়ালা map বানায় — erp_saas এর arrayIndexChange()।
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int|string,array<string,mixed>>
     */
    public static function keyBy(array $rows, string $key = 'id'): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (isset($row[$key])) {
                $out[$row[$key]] = $row;
            }
        }

        return $out;
    }

    /**
     * এক কলামের ভ্যালুগুলো তুলে আনে।
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,mixed>
     */
    public static function pluck(array $rows, string $column): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (array_key_exists($column, $row)) {
                $out[] = $row[$column];
            }
        }

        return $out;
    }

    /**
     * অনেকগুলো row কে একটা কলাম ধরে গ্রুপ করে।
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int|string,array<int,array<string,mixed>>>
     */
    public static function groupBy(array $rows, string $column): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[$row[$column] ?? ''][] = $row;
        }

        return $out;
    }

    /**
     * টাকার অঙ্ক কথায় (ইনভয়েস/ভাউচার প্রিন্টে লাগে)।
     */
    public static function amountInWords(float $amount): string
    {
        $taka  = (int) floor(abs($amount));
        $poisa = (int) round((abs($amount) - $taka) * 100);

        $words = self::numberToWords($taka) . ' Taka';

        if ($poisa > 0) {
            $words .= ' and ' . self::numberToWords($poisa) . ' Poisa';
        }

        return ($amount < 0 ? 'Minus ' : '') . $words . ' Only';
    }

    /**
     * দক্ষিণ এশিয়ার একক (lakh / crore) অনুযায়ী সংখ্যা → কথা।
     */
    public static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        static $ones = [
            1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen',
        ];

        static $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];

        $parts = [];

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred'] as $unit => $label) {
            if ($number >= $unit) {
                $parts[] = self::numberToWords(intdiv($number, $unit)) . ' ' . $label;
                $number %= $unit;
            }
        }

        if ($number > 0) {
            if ($number < 20) {
                $parts[] = $ones[$number];
            } else {
                $word = $tens[intdiv($number, 10)];

                if ($number % 10 > 0) {
                    $word .= '-' . $ones[$number % 10];
                }

                $parts[] = $word;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * "2026-08-01" বা "01-08-2026" → unix timestamp। খালি হলে আজকের দিন।
     */
    public static function toTime(?string $date, ?int $default = null): int
    {
        if ($date === null || trim($date) === '') {
            return $default ?? strtotime('today');
        }

        $time = strtotime(str_replace('/', '-', trim($date)));

        return $time === false ? ($default ?? strtotime('today')) : $time;
    }

    /**
     * ইনপুট স্যানিটাইজ — erp_saas এর sanitize() এর সমতুল্য।
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }

        return is_string($value) ? trim($value) : $value;
    }
}
