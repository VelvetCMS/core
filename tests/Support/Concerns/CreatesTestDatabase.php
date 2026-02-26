<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Concerns;

use VelvetCMS\Database\Connection;

/**
 * Helpers for creating in-memory or file-based SQLite databases in tests.
 *
 * Requires the using class to have a `$tmpDir` property (provided by TestCase).
 */
trait CreatesTestDatabase
{
    /**
     * Create a SQLite Connection backed by a temp file.
     */
    protected function makeSqliteConnection(string $name = 'test'): Connection
    {
        return new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $this->tmpDir . "/{$name}.sqlite",
                ],
            ],
        ]);
    }

    /**
     * Create the canonical `pages` table (full schema with constraints).
     */
    protected function createPagesTable(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug VARCHAR(255) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                layout VARCHAR(100) DEFAULT NULL,
                excerpt TEXT DEFAULT NULL,
                meta TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                published_at DATETIME DEFAULT NULL
            )
        ");
    }

    /**
     * Create the `data_store` table used by DatabaseDataStore.
     */
    protected function createDataStoreTable(\PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE data_store (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection VARCHAR(255) NOT NULL,
                key VARCHAR(255) NOT NULL,
                data TEXT NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE(collection, key)
            )
        ');
    }
}
