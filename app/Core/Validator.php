<?php

namespace App\Core;

/**
 * হালকা ভ্যালিডেটর। ফেল করলে Message কিউতে error ঢুকিয়ে false দেয়,
 * ফলে কন্ট্রোলারে শুধু `if (!Validator::check(...)) return Response::payload();`
 *
 *     Validator::check(Request::all(), [
 *         'amount'        => 'required|numeric|min:0.01',
 *         'debit_ledger'  => 'required|int',
 *         'credit_ledger' => 'required|int',
 *         'date'          => 'required|date',
 *         'note'          => 'max:255',
 *     ]);
 */
final class Validator
{
    /** @var array<string,string> field => প্রথম error */
    private static array $errors = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     */
    public static function check(array $data, array $rules, bool $pushMessages = true): bool
    {
        self::$errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;

            foreach (explode('|', $ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }

                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

                $error = self::apply($field, $value, $name, $arg);

                if ($error !== null) {
                    self::$errors[$field] = $error;
                    break; // প্রতি ফিল্ডে প্রথম error-ই যথেষ্ট
                }
            }
        }

        if ($pushMessages) {
            foreach (self::$errors as $error) {
                Message::error($error);
            }
        }

        return self::$errors === [];
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        return self::$errors;
    }

    private static function apply(string $field, mixed $value, string $rule, ?string $arg): ?string
    {
        $label = str_replace('_', ' ', $field);
        $empty = $value === null || $value === '' || $value === [];

        // required ছাড়া বাকি নিয়ম খালি ভ্যালুতে চলবে না
        if ($rule !== 'required' && $empty) {
            return null;
        }

        return match ($rule) {
            'required' => $empty ? "$label দিতে হবে।" : null,
            'int'      => filter_var($value, FILTER_VALIDATE_INT) === false ? "$label একটি পূর্ণসংখ্যা হতে হবে।" : null,
            'numeric'  => !is_numeric($value) ? "$label একটি সংখ্যা হতে হবে।" : null,
            'email'    => !filter_var($value, FILTER_VALIDATE_EMAIL) ? "$label সঠিক ইমেইল নয়।" : null,
            'date'     => strtotime((string) $value) === false ? "$label সঠিক তারিখ নয়।" : null,
            'array'    => !is_array($value) ? "$label একটি লিস্ট হতে হবে।" : null,
            'min'      => self::compare($value, (float) $arg, '<') ? "$label কমপক্ষে $arg হতে হবে।" : null,
            'max'      => self::compare($value, (float) $arg, '>') ? "$label সর্বোচ্চ $arg হতে পারে।" : null,
            'in'       => !in_array((string) $value, explode(',', (string) $arg), true) ? "$label এর মান সঠিক নয়।" : null,
            default    => null,
        };
    }

    private static function compare(mixed $value, float $limit, string $operator): bool
    {
        $actual = is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);

        return $operator === '<' ? $actual < $limit : $actual > $limit;
    }
}
