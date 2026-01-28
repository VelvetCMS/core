<?php

declare(strict_types=1);

namespace VelvetCMS\Database;

use PDO;
use PDOException;

class Connection
{
    private ?PDO $pdo = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this))->table($table);
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    private function connect(): void
    {
        $connection = $this->config['connections'][$this->config['default']];
        $driver = $connection['driver'];

        try {
            $this->pdo = match ($driver) {
                'sqlite' => $this->connectSqlite($connection),
                'mysql' => $this->connectMysql($connection),
                'pgsql' => $this->connectPgsql($connection),
                default => throw new \RuntimeException("Unsupported driver: {$driver}")
            };

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: {$e->getMessage()}");
        }
    }

    /**
     * Connect to SQLite
     */
    private function connectSqlite(array $config): PDO
    {
        $database = $config['database'];
        $dir = dirname($database);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return new PDO("sqlite:{$database}");
    }

    /**
     * Connect to MySQL
     */
    private function connectMysql(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
            ]
        );
    }

    /**
     * Connect to PostgreSQL
     */
    private function connectPgsql(array $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO(
            $dsn,
            $config['username'],
            $config['password']
        );
    }

    /**
     * Execute raw query
     */
    public function query(string $sql, array $bindings = []): array
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll();
    }

    /**
     * Execute statement (INSERT, UPDATE, DELETE)
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($bindings);
        return $statement->rowCount();
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        try {
            $driver = $this->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

            $sql = match ($driver) {
                'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                'mysql' => 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?",
                default => throw new \RuntimeException("Unsupported driver: {$driver}")
            };

            $result = $this->query($sql, [$table]);

            return count($result) > 0;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the database driver name (sqlite, mysql, pgsql)
     */
    public function getDriver(): string
    {
        return $this->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Execute callback in transaction
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}
