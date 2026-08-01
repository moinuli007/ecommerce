<?php

namespace App\Core;

use InvalidArgumentException;

/**
 * ছোট, prepared-statement ভিত্তিক কোয়েরি বিল্ডার।
 *
 *     DB::table('a_ledgers')
 *         ->where('type', LedgerType::Customer->value)
 *         ->whereIn('id', [1, 2, 3])
 *         ->orderBy('name')
 *         ->get();
 *
 * নোট: রিটার্ন সবসময় plain array (associative), কোনো entity object নয় —
 * প্রজেক্টের রুল অনুযায়ী সব সার্ভিস static এবং array নিয়েই কাজ করে।
 */
final class QueryBuilder
{
    private string $table;

    /** @var array<int,string> */
    private array $columns = ['*'];

    /** @var array<int,string> */
    private array $wheres = [];

    /** @var array<int,mixed> */
    private array $bindings = [];

    /** @var array<int,string> */
    private array $joins = [];

    /** @var array<int,string> */
    private array $orders = [];

    /** @var array<int,string> */
    private array $groups = [];

    private ?string $having = null;

    private ?int $limit = null;

    private ?int $offset = null;

    private bool $forUpdate = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    // -------------------------------------------------------------------------
    // SELECT অংশ
    // -------------------------------------------------------------------------

    public function select(string ...$columns): self
    {
        if ($columns !== []) {
            $this->columns = $columns;
        }

        return $this;
    }

    public function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->joins[] = strtoupper($type) . " JOIN $table ON $on";

        return $this;
    }

    public function leftJoin(string $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    // -------------------------------------------------------------------------
    // WHERE অংশ
    // -------------------------------------------------------------------------

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->wheres[]   = "`" . str_replace('.', '`.`', $column) . "` $operator ?";
        $this->bindings[] = $value;

        return $this;
    }

    /** @param array<int,mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            // খালি লিস্ট মানে কোনো row-ই ম্যাচ করবে না
            $this->wheres[] = '1 = 0';

            return $this;
        }

        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "`" . str_replace('.', '`.`', $column) . "` IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, array_values($values));

        return $this;
    }

    /** @param array{0:mixed,1:mixed} $range */
    public function whereBetween(string $column, array $range): self
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('whereBetween() এ ঠিক দুইটা ভ্যালু লাগবে।');
        }

        $this->wheres[]   = "`" . str_replace('.', '`.`', $column) . "` BETWEEN ? AND ?";
        $this->bindings[] = $range[0];
        $this->bindings[] = $range[1];

        return $this;
    }

    public function whereNull(string $column, bool $not = false): self
    {
        $this->wheres[] = "`" . str_replace('.', '`.`', $column) . '` IS ' . ($not ? 'NOT ' : '') . 'NULL';

        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        $this->wheres[]   = "`" . str_replace('.', '`.`', $column) . "` LIKE ?";
        $this->bindings[] = '%' . $value . '%';

        return $this;
    }

    /**
     * কাঁচা শর্ত। কলাম/অপারেটর কোডে হার্ডকোড করবেন, ইউজার ইনপুট সবসময় $bindings এ।
     *
     * @param array<int,mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = '(' . $sql . ')';
        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * শর্ত সত্য হলেই ক্লোজারটা চলে — Laravel এর when() এর মতো।
     */
    public function when(mixed $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this, $condition);
        }

        return $this;
    }

    public function active(string $column = 'isActive'): self
    {
        return $this->where($column, 1);
    }

    // -------------------------------------------------------------------------
    // বাকি অংশ
    // -------------------------------------------------------------------------

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction      = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "`" . str_replace('.', '`.`', $column) . "` $direction";

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = "`" . str_replace('.', '`.`', $column) . "`";
        }

        return $this;
    }

    public function having(string $sql): self
    {
        $this->having = $sql;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    public function page(int $page, int $perPage): self
    {
        return $this->limit($perPage)->offset(max(0, $page - 1) * $perPage);
    }

    /** SELECT ... FOR UPDATE (ট্রানজেকশনে row lock) */
    public function lockForUpdate(): self
    {
        $this->forUpdate = true;

        return $this;
    }

    // -------------------------------------------------------------------------
    // এক্সিকিউট
    // -------------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function get(): array
    {
        return DB::select($this->toSql(), $this->bindings);
    }

    /** @return array<string,mixed> না পেলে খালি array */
    public function first(): array
    {
        $rows = $this->limit(1)->get();

        return $rows[0] ?? [];
    }

    public function value(string $column, mixed $default = null): mixed
    {
        $row = $this->select($column)->first();

        return $row === [] ? $default : reset($row);
    }

    /** @return array<int,mixed> */
    public function pluck(string $column): array
    {
        return Utility::pluck($this->select($column)->get(), $column);
    }

    public function count(string $column = '*'): int
    {
        $clone          = clone $this;
        $clone->columns = ["COUNT($column) AS aggregate"];
        $clone->orders  = [];
        $clone->limit   = null;
        $clone->offset  = null;

        return (int) (DB::selectOne($clone->toSql(), $clone->bindings)['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $clone          = clone $this;
        $clone->columns = ["COALESCE(SUM(`$column`), 0) AS aggregate"];
        $clone->orders  = [];
        $clone->limit   = null;
        $clone->offset  = null;

        return (float) (DB::selectOne($clone->toSql(), $clone->bindings)['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): int
    {
        if ($data === [] || $this->wheres === []) {
            return 0;
        }

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "`$column` = ?";
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set)
             . ' WHERE ' . implode(' AND ', $this->wheres);

        DB::run($sql, array_merge(array_values($data), $this->bindings));

        return DB::affectedRows();
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            return 0;
        }

        DB::run(
            "DELETE FROM `{$this->table}` WHERE " . implode(' AND ', $this->wheres),
            $this->bindings
        );

        return DB::affectedRows();
    }

    // -------------------------------------------------------------------------
    // SQL তৈরি
    // -------------------------------------------------------------------------

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM `{$this->table}`";

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        if ($this->forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $sql;
    }

    /** @return array<int,mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
