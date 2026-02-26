<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Content;

use VelvetCMS\Database\Connection;
use VelvetCMS\Drivers\Content\DBDriver;
use VelvetCMS\Exceptions\NotFoundException;
use VelvetCMS\Models\Page;
use VelvetCMS\Tests\Support\Concerns\CreatesTestDatabase;
use VelvetCMS\Tests\Support\TestCase;

final class DBDriverTest extends TestCase
{
    use CreatesTestDatabase;

    private DBDriver $driver;
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->makeSqliteConnection('db');
        $this->createPagesTable($this->db->getPdo());

        $this->driver = new DBDriver($this->db);
    }

    public function test_can_save_and_load_page(): void
    {
        $page = new Page(
            slug: 'test-db',
            title: 'Test DB Page',
            content: '# Database Storage',
            status: 'published'
        );

        $result = $this->driver->save($page);
        $this->assertTrue($result);

        $loaded = $this->driver->load('test-db');

        $this->assertSame('test-db', $loaded->slug);
        $this->assertSame('Test DB Page', $loaded->title);
        $this->assertSame('# Database Storage', $loaded->content);
        $this->assertSame('published', $loaded->status);
    }

    public function test_can_update_existing_page(): void
    {
        $page = new Page('update-test', 'Original', 'Original content');
        $this->driver->save($page);

        // Update
        $page->title = 'Updated';
        $page->content = 'Updated content';
        $this->driver->save($page);

        $loaded = $this->driver->load('update-test');

        $this->assertSame('Updated', $loaded->title);
        $this->assertSame('Updated content', $loaded->content);
    }

    public function test_stores_metadata_as_json(): void
    {
        $page = new Page('meta-test', 'Meta', 'Content');
        $page->setMeta('author', 'Velvet');
        $page->setMeta('tags', ['a', 'b']);

        $this->driver->save($page);

        // Verify raw DB content
        $stmt = $this->db->getPdo()->prepare('SELECT meta FROM pages WHERE slug = ?');
        $stmt->execute(['meta-test']);
        $rawMeta = $stmt->fetchColumn();

        $this->assertJson($rawMeta);
        $decoded = json_decode($rawMeta, true);
        $this->assertSame('Velvet', $decoded['author']);
        $this->assertSame(['a', 'b'], $decoded['tags']);

        // Verify loaded object
        $loaded = $this->driver->load('meta-test');
        $this->assertSame('Velvet', $loaded->getMeta('author'));
    }

    public function test_throws_not_found_exception_for_missing_page(): void
    {
        $this->expectException(NotFoundException::class);
        $this->driver->load('non-existent');
    }

    public function test_can_delete_page(): void
    {
        $page = new Page('delete-me', 'Delete Me', 'Content');
        $this->driver->save($page);

        $this->assertTrue($this->driver->delete('delete-me'));

        $this->expectException(NotFoundException::class);
        $this->driver->load('delete-me');
    }
}
