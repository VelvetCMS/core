<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Content;

use VelvetCMS\Drivers\Cache\FileCache;
use VelvetCMS\Drivers\Content\FileDriver;
use VelvetCMS\Models\Page;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Tests\Support\TestCase;

final class FileDriverTest extends TestCase
{
    private function driver(): FileDriver
    {
        $cache = new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => 'test',
        ]);
        $commonMark = new \VelvetCMS\Services\Parsers\CommonMarkParser();

        return new FileDriver(new ContentParser($cache, $commonMark), $this->tmpDir . '/content/pages');
    }

    public function test_save_load_and_delete_page(): void
    {
        $driver = $this->driver();
        $page = new Page('welcome', 'Welcome', 'Hello world', 'published');

        $this->assertTrue($driver->save($page));
        $this->assertTrue($driver->exists('welcome'));

        $loaded = $driver->load('welcome');
        $this->assertSame('Welcome', $loaded->title);
        $this->assertStringContainsString('Hello world', $loaded->content);

        $this->assertTrue($driver->delete('welcome'));
        $this->assertFalse($driver->exists('welcome'));
    }

    public function test_throws_exception_when_loading_nonexistent_page(): void
    {
        $driver = $this->driver();
        $this->expectException(\VelvetCMS\Exceptions\NotFoundException::class);
        $driver->load('nonexistent');
    }

    public function test_throws_exception_when_saving_invalid_page(): void
    {
        $driver = $this->driver();
        $this->expectException(\VelvetCMS\Exceptions\ValidationException::class);
        
        // Page with empty slug
        $page = new Page(
            slug: '',
            title: 'Test',
            content: 'Content'
        );
        
        $driver->save($page);
    }

    public function test_can_update_existing_page(): void
    {
        $driver = $this->driver();
        $page = new Page('update-test', 'Original', 'Original content');
        $driver->save($page);
        
        // Update
        $page->title = 'Updated';
        $page->content = 'Updated content';
        $driver->save($page);
        
        $loaded = $driver->load('update-test');
        
        $this->assertSame('Updated', $loaded->title);
        $this->assertSame('Updated content', $loaded->content);
    }
}
