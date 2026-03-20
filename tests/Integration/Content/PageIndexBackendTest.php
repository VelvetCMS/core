<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Content;

use VelvetCMS\Content\Index\JsonPageIndex;
use VelvetCMS\Content\Index\PageIndexer;
use VelvetCMS\Content\Index\SqlitePageIndex;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Models\Page;
use VelvetCMS\Tests\Support\Concerns\CreatesContentParser;
use VelvetCMS\Tests\Support\TestCase;

final class PageIndexBackendTest extends TestCase
{
    use CreatesContentParser;

    private function driver(string $backend = 'json'): FileDriver
    {
        $contentPath = $this->tmpDir . '/content/pages';
        $index = match ($backend) {
            'sqlite' => new SqlitePageIndex($this->pageIndexSqlitePath()),
            default => new JsonPageIndex($this->pageIndexJsonPath()),
        };

        return new FileDriver(
            $this->makeContentParser(),
            $index,
            new PageIndexer(),
            $contentPath,
        );
    }

    public function test_json_index_rebuilds_from_corrupt_index_file(): void
    {
        $driver = $this->driver('json');
        $page = new Page('welcome', 'Welcome', 'Hello world', 'published');
        $this->assertTrue($driver->save($page));

        file_put_contents($this->pageIndexJsonPath(), '{broken json');

        $freshDriver = $this->driver('json');
        $loaded = $freshDriver->load('welcome');

        $this->assertSame('Welcome', $loaded->title);
        $this->assertTrue($freshDriver->exists('welcome'));

        $rebuilt = json_decode((string) file_get_contents($this->pageIndexJsonPath()), true);
        $this->assertIsArray($rebuilt);
        $this->assertArrayHasKey('pages', $rebuilt);
        $this->assertArrayHasKey('welcome', $rebuilt['pages']);
    }

    public function test_sqlite_index_backend_supports_file_native_pages(): void
    {
        $driver = $this->driver('sqlite');
        $driver->save(new Page('published-page', 'Published Page', 'Visible', 'published'));
        $driver->save(new Page('draft-page', 'Draft Page', 'Hidden', 'draft'));

        $loaded = $driver->load('published-page');
        $published = $driver->list(['status' => 'published']);

        $this->assertSame('Published Page', $loaded->title);
        $this->assertSame(1, $published->count());
        $this->assertSame(2, $driver->count());
        $this->assertFileExists($this->pageIndexSqlitePath());

        $this->assertTrue($driver->delete('draft-page'));
        $this->assertFalse($driver->exists('draft-page'));
        $this->assertSame(1, $driver->count());
    }

    public function test_json_index_entries_contain_no_body_or_html(): void
    {
        $driver = $this->driver('json');
        $driver->save(new Page('lean', 'Lean Page', 'Some content here', 'published'));

        $raw = json_decode((string) file_get_contents($this->pageIndexJsonPath()), true);
        $entry = $raw['pages']['lean'] ?? [];

        $this->assertArrayNotHasKey('body', $entry);
        $this->assertArrayNotHasKey('html', $entry);
        $this->assertSame('Lean Page', $entry['title']);
    }
}
