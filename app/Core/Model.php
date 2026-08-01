<?php

namespace App\Core;

/**
 * বেস মডেল — পুরোটাই static, কোনো ইনস্ট্যান্স নাই।
 * সাবক্লাসে শুধু `protected static string $table` দিলেই হয়।
 *
 *     Ledger::find(5);
 *     Ledger::where('type', 12)->get();
 *     Ledger::create(['name' => 'Cash', ...]);
 */
abstract class Model
{
    protected static string $table;

    /** isActive কলামের নাম (কোনো টেবিলে ভিন্ন হলে সাবক্লাসে ওভাররাইড) */
    protected static string $activeColumn = 'isActive';

    public static function table(): string
    {
        return static::$table;
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::$table);
    }

    public static function active(): QueryBuilder
    {
        return static::query()->active(static::$activeColumn);
    }

    /** @return array<string,mixed> না পেলে খালি array */
    public static function find(int $id): array
    {
        return DB::getById(static::$table, $id);
    }

    /**
     * @param  array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    public static function findMany(array $ids): array
    {
        return static::query()->whereIn('id', $ids)->get();
    }

    public static function where(string $column, mixed $value, string $operator = '='): QueryBuilder
    {
        return static::query()->where($column, $value, $operator);
    }

    /** @param array<int,mixed> $values */
    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * created_at/created_by নিজে বসিয়ে insert করে।
     *
     * @param  array<string,mixed> $data
     * @return int নতুন id
     */
    public static function create(array $data): int
    {
        Utility::stampCreate($data);

        return DB::insert(static::$table, $data);
    }

    /**
     * updated_at/updated_by নিজে বসিয়ে update করে।
     *
     * @param  array<string,mixed> $data
     * @return int affected rows
     */
    public static function updateById(int $id, array $data): int
    {
        Utility::stampUpdate($data);

        return DB::update(static::$table, $data, ['id' => $id]);
    }

    public static function deleteById(int $id): int
    {
        return DB::delete(static::$table, ['id' => $id]);
    }

    /**
     * where দিয়ে খুঁজে না পেলে বানায়।
     *
     * @param  array<string,mixed> $where
     * @param  array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public static function firstOrCreate(array $where, array $extra = []): array
    {
        $row = DB::getRow(static::$table, $where);

        if ($row !== []) {
            return $row;
        }

        $id = static::create($where + $extra);

        return static::find($id);
    }
}
