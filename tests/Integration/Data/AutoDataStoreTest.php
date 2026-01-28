<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Data;

use PDO;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Data\AutoDataStore;
use VelvetCMS\Tests\Support\TestCase;

final class AutoDataStoreTest extends TestCase
{
    private AutoDataStore $store;
    private Connection $db;
    private string $fileDataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileDataPath = $this->tmpDir . '/data';
        $dbPath = $this->tmpDir . '/data.sqlite';

        $pdo = new PDO('sqlite:' . $dbPath);
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

        $this->store = new AutoDataStore($this->db, $this->fileDataPath);
    }

    public function test_writes_to_both_stores(): void
    {
        $data = ['sync' => true];
        $this->store->put('test', 'sync', $data);

        // Check DB
        $dbRow = $this->db->table('data_store')
            ->where('collection', '=', 'test')
            ->where('key', '=', 'sync')
            ->first();
        $this->assertNotNull($dbRow);

        // Check File
        $this->assertFileExists($this->fileDataPath . '/test/sync.json');
    }

    public function test_reads_from_db_if_available(): void
    {
        // Manually insert into DB with specific value
        $this->db->table('data_store')->insert([
            'collection' => 'read',
            'key' => 'source',
            'data' => json_encode(['from' => 'db']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Manually create file with different value
        mkdir($this->fileDataPath . '/read', 0755, true);
        file_put_contents(
            $this->fileDataPath . '/read/source.json',
            json_encode(['from' => 'file'])
        );

        $data = $this->store->get('read', 'source');

        // Should prefer DB
        $this->assertSame('db', $data['from']);
    }

    public function test_falls_back_to_file_if_db_missing_record(): void
    {
        // Only in file
        mkdir($this->fileDataPath . '/fallback', 0755, true);
        file_put_contents(
            $this->fileDataPath . '/fallback/key.json',
            json_encode(['found' => true])
        );

        $data = $this->store->get('fallback', 'key');
        $this->assertTrue($data['found']);
    }
}
