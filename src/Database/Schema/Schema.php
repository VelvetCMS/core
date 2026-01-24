<?php

declare(strict_types=1);

namespace VelvetCMS\Database\Schema;

use VelvetCMS\Database\Connection;
use VelvetCMS\Database\Schema\Grammars\Grammar;
use VelvetCMS\Database\Schema\Grammars\MySqlGrammar;
use VelvetCMS\Database\Schema\Grammars\SQLiteGrammar;
use VelvetCMS\Database\Schema\Grammars\PostgresGrammar;

class Schema
{
    private static ?Connection $connection = null;
    private static ?Grammar $grammar = null;

    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
        self::$grammar = self::getGrammar($connection);
    }

    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->create();
        $callback($blueprint);

        self::execute($blueprint);
    }

    public static function drop(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->drop();
        
        self::execute($blueprint);
    }

    public static function dropIfExists(string $table): void
    {
        if (self::$connection->tableExists($table)) {
            self::drop($table);
        }
    }

    private static function execute(Blueprint $blueprint): void
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Schema connection not set.');
        }

        $statements = self::$grammar->compile($blueprint);

        foreach ($statements as $statement) {
            self::$connection->statement($statement);
        }
    }

    private static function getGrammar(Connection $connection): Grammar
    {
        $driver = $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => throw new \RuntimeException("Unsupported driver for Schema Builder: {$driver}"),
        };
    }
}
