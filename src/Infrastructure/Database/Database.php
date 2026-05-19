<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

/**
 * Thin wrapper around a PDO connection to a single SQLite database file.
 *
 * If not using `:memory`, the specified location is treated as a filesystem path
 * and the directory is created on demand. The database file itself is created by
 * SQLite when the first connection is made.
 */
class Database
{
    private readonly PDO $pdo;

    private readonly string $location;

    public function __construct(string $location)
    {
        $this->location = $location;

        if ($location !== ':memory:') {
            $dir = dirname($location);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Could not create database directory: {$dir}");
                }
            }
        }

        $dsn = 'sqlite:' . $location;

        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');

        if ($location !== ':memory:') {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * Prepare and execute a statement, returning the underlying PDOStatement.
     *
     * Because the connection runs with ERRMODE_EXCEPTION, prepare()/execute()
     * never return false in practice. This wrapper exists primarily to give
     * static analysers a non-nullable PDOStatement back.
     *
     * @param array<string|int, scalar|null> $params
     */
    public function prepared(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row as an associative array, or null when no row matches.
     *
     * @param array<string|int, scalar|null> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepared($sql, $params);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * Fetch all matching rows as a list of associative arrays.
     *
     * @param array<string|int, scalar|null> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepared($sql, $params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE/DDL statement and return the affected
     * row count.
     *
     * @param array<string|int, scalar|null> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->prepared($sql, $params)->rowCount();
    }

    /**
     * Run $callback inside a transaction; commit on success, rollback on
     * any throwable.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
