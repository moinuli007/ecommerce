<?php

namespace App\Core;

use mysqli;
use mysqli_result;
use RuntimeException;
use Throwable;

/**
 * mysqli র‍্যাপার — পুরোটাই static (প্রজেক্ট রুল: সব অবজেক্ট static)।
 *
 * erp_saas এর app/Methods/DB.php এর সাথে API প্রায় একই, তবে একটা বড় পার্থক্য:
 * এখানে সব কোয়েরি **prepared statement** দিয়ে চলে, string concatenation নয়।
 * ফলে SQL injection এর সুযোগ নাই এবং টাইপ (int/float/string) ঠিক থাকে।
 */
final class DB
{
    private static ?mysqli $conn = null;

    /** @var array<int,array{sql:string,bindings:array,ms:float}> */
    private static array $log = [];

    private static int $txLevel = 0;

    private const LOG_LIMIT = 200;

    // -------------------------------------------------------------------------
    // কানেকশন
    // -------------------------------------------------------------------------

    public static function connection(): mysqli
    {
        if (self::$conn === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            self::$conn = new mysqli(
                Env::get('DB_SERVER', '127.0.0.1'),
                Env::get('DB_USERNAME', 'root'),
                Env::get('DB_PASSWORD', ''),
                Env::get('DB_DATABASE', '')
            );

            self::$conn->set_charset('utf8mb4');
        }

        return self::$conn;
    }

    public static function close(): void
    {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }

    // -------------------------------------------------------------------------
    // নিচু লেভেলের রান
    // -------------------------------------------------------------------------

    /**
     * @param  array<int,mixed> $bindings
     * @return mysqli_result|bool
     */
    public static function run(string $sql, array $bindings = [])
    {
        $conn  = self::connection();
        $start = microtime(true);

        try {
            if ($bindings === []) {
                $result = $conn->query($sql);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(self::typeString($bindings), ...$bindings);
                $stmt->execute();
                $result = $stmt->get_result();

                self::$lastAffected   = $stmt->affected_rows;
                self::$lastInsertedId = (int) $conn->insert_id;

                $stmt->close();
            }
        } catch (Throwable $e) {
            self::remember($sql, $bindings, $start);
            throw new RuntimeException(
                'DB error: ' . $e->getMessage() . ' | SQL: ' . $sql,
                (int) $e->getCode(),
                $e
            );
        }

        if ($bindings === []) {
            self::$lastAffected   = $conn->affected_rows;
            self::$lastInsertedId = (int) $conn->insert_id;
        }

        self::remember($sql, $bindings, $start);

        return $result;
    }

    private static int $lastAffected   = 0;
    private static int $lastInsertedId = 0;

    public static function affectedRows(): int
    {
        return self::$lastAffected;
    }

    public static function lastInsertId(): int
    {
        return self::$lastInsertedId;
    }

    // -------------------------------------------------------------------------
    // SELECT
    // -------------------------------------------------------------------------

    /**
     * @param  array<int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $result = self::run($sql, $bindings);

        if (!$result instanceof mysqli_result) {
            return [];
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    /**
     * @param  array<int,mixed> $bindings
     * @return array<string,mixed>  না পেলে খালি array
     */
    public static function selectOne(string $sql, array $bindings = []): array
    {
        $rows = self::select($sql, $bindings);

        return $rows[0] ?? [];
    }

    /**
     * এক কলামের একটামাত্র ভ্যালু।
     * @param array<int,mixed> $bindings
     */
    public static function scalar(string $sql, array $bindings = [], mixed $default = null): mixed
    {
        $row = self::selectOne($sql, $bindings);

        return $row === [] ? $default : reset($row);
    }

    // -------------------------------------------------------------------------
    // INSERT / UPDATE / DELETE
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return int  নতুন row এর id (auto increment না থাকলে affected rows)
     */
    public static function insert(string $table, array $data): int
    {
        if ($data === []) {
            throw new RuntimeException("Insert into `$table` has no data");
        }

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = '`' . implode('`, `', $columns) . '`';

        $sql = "INSERT INTO `$table` ($columnList) VALUES ($placeholders)";

        self::run($sql, array_values($data));

        return self::$lastInsertedId ?: self::$lastAffected;
    }

    /**
     * একসাথে অনেক row।
     * @param array<int,array<string,mixed>> $rows
     */
    public static function insertMany(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns    = array_keys($rows[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';
        $single     = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $sql = "INSERT INTO `$table` ($columnList) VALUES "
             . implode(', ', array_fill(0, count($rows), $single));

        self::run($sql, $bindings);

        return self::$lastAffected;
    }

    /**
     * @param  array<string,mixed> $data
     * @param  array<string,mixed> $where
     * @return int affected rows
     */
    public static function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }
        if ($where === []) {
            throw new RuntimeException("Cannot update `$table` without a where condition");
        }

        $set    = [];
        $clause = [];

        foreach (array_keys($data) as $column) {
            $set[] = "`$column` = ?";
        }
        foreach (array_keys($where) as $column) {
            $clause[] = "`$column` = ?";
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $set)
             . ' WHERE ' . implode(' AND ', $clause);

        self::run($sql, array_merge(array_values($data), array_values($where)));

        return self::$lastAffected;
    }

    /**
     * @param  array<string,mixed> $where
     * @return int affected rows
     */
    public static function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException("Cannot delete from `$table` without a where condition");
        }

        $clause = [];
        foreach (array_keys($where) as $column) {
            $clause[] = "`$column` = ?";
        }

        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $clause);

        self::run($sql, array_values($where));

        return self::$lastAffected;
    }

    // -------------------------------------------------------------------------
    // শর্টকাট
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function getById(string $table, int $id): array
    {
        return self::selectOne("SELECT * FROM `$table` WHERE `id` = ? LIMIT 1", [$id]);
    }

    /**
     * @param  array<string,mixed> $where
     * @return array<string,mixed>
     */
    public static function getRow(string $table, array $where): array
    {
        $clause = [];
        foreach (array_keys($where) as $column) {
            $clause[] = "`$column` = ?";
        }

        $sql = "SELECT * FROM `$table` WHERE " . implode(' AND ', $clause) . ' LIMIT 1';

        return self::selectOne($sql, array_values($where));
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    // -------------------------------------------------------------------------
    // ট্রানজেকশন (নেস্টেড কল savepoint দিয়ে হ্যান্ডেল হয়)
    // -------------------------------------------------------------------------

    public static function begin(): void
    {
        if (self::$txLevel === 0) {
            self::connection()->begin_transaction();
        } else {
            self::connection()->query('SAVEPOINT sp_' . self::$txLevel);
        }

        self::$txLevel++;
    }

    public static function commit(): void
    {
        if (self::$txLevel === 0) {
            return;
        }

        self::$txLevel--;

        if (self::$txLevel === 0) {
            self::connection()->commit();
        } else {
            self::connection()->query('RELEASE SAVEPOINT sp_' . self::$txLevel);
        }
    }

    public static function rollback(): void
    {
        if (self::$txLevel === 0) {
            return;
        }

        self::$txLevel--;

        if (self::$txLevel === 0) {
            self::connection()->rollback();
        } else {
            self::connection()->query('ROLLBACK TO SAVEPOINT sp_' . self::$txLevel);
        }
    }

    /**
     * ক্লোজারের ভেতরে exception হলে পুরোটা rollback হবে।
     */
    public static function transaction(callable $callback): mixed
    {
        self::begin();

        try {
            $result = $callback();
            self::commit();

            return $result;
        } catch (Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // ডিবাগ
    // -------------------------------------------------------------------------

    /** @return array<int,array{sql:string,bindings:array,ms:float}> */
    public static function log(): array
    {
        return self::$log;
    }

    private static function remember(string $sql, array $bindings, float $start): void
    {
        if (count(self::$log) >= self::LOG_LIMIT) {
            return;
        }

        self::$log[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'ms'       => round((microtime(true) - $start) * 1000, 3),
        ];
    }

    /**
     * bind_param এর জন্য "isds..." টাইপ স্ট্রিং বানায়।
     * @param array<int,mixed> $bindings
     */
    private static function typeString(array $bindings): string
    {
        $types = '';

        foreach ($bindings as $value) {
            $types .= match (true) {
                is_int($value)   => 'i',
                is_float($value) => 'd',
                is_bool($value)  => 'i',
                default          => 's',
            };
        }

        return $types;
    }
}
