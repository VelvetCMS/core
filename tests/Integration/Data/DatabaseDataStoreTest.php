<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Data;

use PDO;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Data\DatabaseDataStore;
use VelvetCMS\Tests\Support\TestCase;

final class DatabaseDataStoreTest extends TestCase
{
    private DatabaseDataStore $store;
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        $dbPath = $this->tmpDir . '/data.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);

        // Create data_store table
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

        $this->db = new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                ],
            ],
        ]);

        $this->store = new DatabaseDataStore($this->db);
    }

    public function test_can_put_and_get_data(): void
    {
        $data = ['name' => 'DB Test', 'count' => 42];
        $this->store->put('metrics', 'daily', $data);

        $retrieved = $this->store->get('metrics', 'daily');

        // DB store adds metadata keys
        $this->assertSame('DB Test', $retrieved['name']);
        $this->assertSame(42, $retrieved['count']);
        $this->assertSame('daily', $retrieved['_key']);
        $this->assertArrayHasKey('_updated_at', $retrieved);
    }

    public function test_updates_existing_record(): void
    {
        $this->store->put('config', 'app', ['debug' => true]);
        $this->store->put('config', 'app', ['debug' => false]);

        $data = $this->store->get('config', 'app');
        $this->assertFalse($data['debug']);

        // Verify only one record exists
        $count = $this->db->table('data_store')->count();
        $this->assertSame(1, $count);
    }

    public function test_can_forget_item(): void
    {
        $this->store->put('temp', 'foo', ['bar' => 'baz']);
        $this->assertTrue($this->store->forget('temp', 'foo'));

        $this->assertNull($this->store->get('temp', 'foo'));
        $this->assertFalse($this->store->forget('temp', 'foo'));
    }

    public function test_has_checks_existence(): void
    {
        $this->store->put('check', 'me', []);
        $this->assertTrue($this->store->has('check', 'me'));
        $this->assertFalse($this->store->has('check', 'not-me'));
    }
}
