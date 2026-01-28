<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Content;

use PDO;
use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Cache\FileCache;
use VelvetCMS\Drivers\Content\AutoDriver;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Drivers\Content\HybridDriver;
use VelvetCMS\Models\Page;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Tests\Support\TestCase;

final class AutoDriverTest extends TestCase
{
    private function makeConnection(): Connection
    {
        $dbPath = $this->tmpDir . '/db.sqlite';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->exec('CREATE TABLE pages (slug TEXT PRIMARY KEY, title TEXT, content TEXT, status TEXT, layout TEXT, excerpt TEXT, meta TEXT, created_at TEXT, updated_at TEXT, published_at TEXT)');

        return new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                ],
            ],
        ]);
    }

    private function makeAutoDriver(int $threshold = 1): AutoDriver
    {
        $cache = new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => 'test',
        ]);
        $commonMark = new \VelvetCMS\Services\Parsers\CommonMarkParser();
        $parser = new ContentParser($cache, $commonMark);
        $conn = $this->makeConnection();
        $contentPath = $this->tmpDir . '/content/pages';

        return new AutoDriver(
            new FileDriver($parser, $contentPath),
            new HybridDriver($parser, $conn, $contentPath),
            $conn,
            $threshold
        );
    }

    public function test_switches_to_hybrid_after_threshold(): void
    {
        $driver = $this->makeAutoDriver(1);

        // First save triggers evaluation and hits threshold -> hybrid
        $driver->save(new Page('one', 'One', 'File content'));
        $this->assertSame('hybrid', $driver->getActiveDriverName());

        // Subsequent saves stay on hybrid
        $driver->save(new Page('two', 'Two', 'Db content'));
        $this->assertSame('hybrid', $driver->getActiveDriverName());

        $loaded = $driver->load('two');
        $this->assertSame('Two', $loaded->title);
    }

    public function test_uses_file_driver_when_below_threshold(): void
    {
        $driver = $this->makeAutoDriver(5);

        // Create 3 pages (below threshold of 5)
        for ($i = 1; $i <= 3; $i++) {
            $page = new Page(
                slug: "page-{$i}",
                title: "Page {$i}",
                content: "Content {$i}",
                status: 'published'
            );
            $driver->save($page);
        }

        // Should be using FileDriver
        $this->assertSame('file', $driver->getActiveDriverName());

        // Verify files exist
        $contentPath = $this->tmpDir . '/content/pages';
        $this->assertFileExists($contentPath . '/page-1.md');
        $this->assertFileExists($contentPath . '/page-2.md');
        $this->assertFileExists($contentPath . '/page-3.md');
    }

    public function test_load_works_across_driver_switch(): void
    {
        $driver = $this->makeAutoDriver(5);

        // Create page with FileDriver
        $page = new Page(
            slug: 'test-page',
            title: 'Test Page',
            content: '# Test Content',
            status: 'published'
        );
        $driver->save($page);

        $this->assertSame('file', $driver->getActiveDriverName());

        // Load should work
        $loaded = $driver->load('test-page');
        $this->assertSame('Test Page', $loaded->title);
        $this->assertSame('# Test Content', $loaded->content);
    }

    public function test_list_works_with_active_driver(): void
    {
        $driver = $this->makeAutoDriver(5);

        // Create multiple pages
        for ($i = 1; $i <= 3; $i++) {
            $page = new Page(
                slug: "list-page-{$i}",
                title: "List Page {$i}",
                content: "Content {$i}",
                status: 'published'
            );
            $driver->save($page);
        }

        $pages = $driver->list(['status' => 'published']);

        $this->assertCount(3, $pages);
    }

    public function test_delete_works_with_active_driver(): void
    {
        $driver = $this->makeAutoDriver(5);

        $page = new Page(
            slug: 'delete-me',
            title: 'Delete Me',
            content: 'Content',
            status: 'draft'
        );
        $driver->save($page);

        $this->assertTrue($driver->exists('delete-me'));

        $driver->delete('delete-me');

        $this->assertFalse($driver->exists('delete-me'));
    }

    public function test_count_reflects_current_pages(): void
    {
        $driver = $this->makeAutoDriver(5);

        $this->assertSame(0, $driver->count());

        for ($i = 1; $i <= 3; $i++) {
            $page = new Page(
                slug: "count-page-{$i}",
                title: "Count Page {$i}",
                content: "Content {$i}",
                status: 'published'
            );
            $driver->save($page);
        }

        $this->assertSame(3, $driver->count());
    }
}
