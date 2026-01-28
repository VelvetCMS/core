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

    private function makeFileDriver(): FileDriver
    {
        $cache = new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => 'test',
        ]);
        $commonMark = new \VelvetCMS\Services\Parsers\CommonMarkParser();
        $parser = new ContentParser($cache, $commonMark);
        $contentPath = $this->tmpDir . '/content/pages';

        return new FileDriver($parser, $contentPath);
    }

    private function makeHybridDriver(): HybridDriver
    {
        $cache = new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => 'test',
        ]);
        $commonMark = new \VelvetCMS\Services\Parsers\CommonMarkParser();
        $parser = new ContentParser($cache, $commonMark);
        $conn = $this->makeConnection();
        $contentPath = $this->tmpDir . '/content/pages';

        return new HybridDriver($parser, $conn, $contentPath);
    }

    private function makeAutoDriver(int $threshold = 5, ?int $existingPages = null): AutoDriver
    {
        $fileDriver = $this->makeFileDriver();

        // Pre-populate pages if specified (to test boot-time selection)
        if ($existingPages !== null) {
            for ($i = 1; $i <= $existingPages; $i++) {
                $page = new Page(
                    slug: "existing-{$i}",
                    title: "Existing {$i}",
                    content: "Content {$i}",
                    status: 'published'
                );
                $fileDriver->save($page);
            }
        }

        // Create fresh FileDriver for AutoDriver (so it sees the files)
        $freshFileDriver = $this->makeFileDriver();
        $hybridDriver = $this->makeHybridDriver();

        return new AutoDriver($freshFileDriver, $hybridDriver, null, $threshold);
    }

    public function test_uses_file_driver_when_below_threshold_at_boot(): void
    {
        $driver = $this->makeAutoDriver(threshold: 5, existingPages: 3);

        $this->assertSame('file', $driver->getActiveDriverName());
        $this->assertFalse($driver->isOverThreshold());
    }

    public function test_uses_hybrid_driver_when_at_threshold_at_boot(): void
    {
        $driver = $this->makeAutoDriver(threshold: 5, existingPages: 5);

        $this->assertSame('hybrid', $driver->getActiveDriverName());
        $this->assertTrue($driver->isOverThreshold());
    }

    public function test_uses_hybrid_driver_when_above_threshold_at_boot(): void
    {
        $driver = $this->makeAutoDriver(threshold: 5, existingPages: 10);

        $this->assertSame('hybrid', $driver->getActiveDriverName());
        $this->assertTrue($driver->isOverThreshold());
    }

    public function test_driver_does_not_switch_after_boot(): void
    {
        // Start below threshold
        $driver = $this->makeAutoDriver(threshold: 5, existingPages: 2);
        $this->assertSame('file', $driver->getActiveDriverName());

        // Add pages to exceed threshold
        for ($i = 1; $i <= 5; $i++) {
            $page = new Page(
                slug: "new-page-{$i}",
                title: "New Page {$i}",
                content: "Content {$i}",
                status: 'published'
            );
            $driver->save($page);
        }

        // Should still be file driver (no runtime switching)
        $this->assertSame('file', $driver->getActiveDriverName());
    }

    public function test_load_works_with_selected_driver(): void
    {
        $driver = $this->makeAutoDriver(threshold: 10, existingPages: 3);

        $page = new Page(
            slug: 'test-page',
            title: 'Test Page',
            content: '# Test Content',
            status: 'published'
        );
        $driver->save($page);

        $loaded = $driver->load('test-page');
        $this->assertSame('Test Page', $loaded->title);
        $this->assertSame('# Test Content', $loaded->content);
    }

    public function test_list_works_with_selected_driver(): void
    {
        $driver = $this->makeAutoDriver(threshold: 10, existingPages: 0);

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

    public function test_delete_works_with_selected_driver(): void
    {
        $driver = $this->makeAutoDriver(threshold: 10, existingPages: 0);

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
        $driver = $this->makeAutoDriver(threshold: 10, existingPages: 0);

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

    public function test_empty_site_uses_file_driver(): void
    {
        $driver = $this->makeAutoDriver(threshold: 5, existingPages: 0);

        $this->assertSame('file', $driver->getActiveDriverName());
        $this->assertFalse($driver->isOverThreshold());
    }
}
