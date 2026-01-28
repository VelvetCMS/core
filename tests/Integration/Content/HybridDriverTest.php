<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Content;

use PDO;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Cache\FileCache;
use VelvetCMS\Drivers\Content\HybridDriver;
use VelvetCMS\Models\Page;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Tests\Support\TestCase;

final class HybridDriverTest extends TestCase
{
    private Connection $db;

    private function makeConnection(): Connection
    {
        $dbPath = $this->tmpDir . '/db.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE pages (slug TEXT PRIMARY KEY, title TEXT, content TEXT, status TEXT, layout TEXT, excerpt TEXT, meta TEXT, created_at TEXT, updated_at TEXT, published_at TEXT)');

        $this->db = new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                ],
            ],
        ]);

        return $this->db;
    }

    private function driver(): HybridDriver
    {
        $cache = new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => 'test',
        ]);
        $commonMark = new \VelvetCMS\Services\Parsers\CommonMarkParser();

        return new HybridDriver(
            new ContentParser($cache, $commonMark),
            $this->makeConnection(),
            $this->tmpDir . '/content/pages'
        );
    }

    public function test_save_load_and_delete(): void
    {
        $driver = $this->driver();
        $page = new Page('about', 'About', 'Body text', 'published');

        $this->assertTrue($driver->save($page));
        $this->assertTrue($driver->exists('about'));

        $loaded = $driver->load('about');
        $this->assertSame('About', $loaded->title);
        $this->assertSame('Body text', $loaded->content);

        $this->assertTrue($driver->delete('about'));
        $this->assertFalse($driver->exists('about'));
    }

    public function test_saves_content_to_file_and_metadata_to_db(): void
    {
        $driver = $this->driver();
        $page = new Page(
            slug: 'hybrid-test',
            title: 'Hybrid Test',
            content: '# Hybrid Storage',
            status: 'published'
        );

        $driver->save($page);

        // Check file exists
        $filepath = $this->tmpDir . '/content/pages/hybrid-test.md';
        $this->assertFileExists($filepath);

        // Check file contains content
        $fileContent = file_get_contents($filepath);
        $this->assertStringContainsString('# Hybrid Storage', $fileContent);

        // Check DB contains metadata
        $stmt = $this->db->getPdo()->prepare('SELECT title, status FROM pages WHERE slug = ?');
        $stmt->execute(['hybrid-test']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('Hybrid Test', $row['title']);
        $this->assertSame('published', $row['status']);
    }
}
