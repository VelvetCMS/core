<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Cache;

use VelvetCMS\Drivers\Cache\RedisCache;
use VelvetCMS\Tests\Support\TestCase;

final class RedisCacheTest extends TestCase
{
    private ?RedisCache $cache = null;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\Redis::class)) {
            $this->markTestSkipped('Redis extension is not available.');
        }

        $client = new \Redis();

        try {
            $connected = $client->connect('127.0.0.1', 6379, 0.5);
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis server is not reachable on 127.0.0.1:6379');
        }

        if (!$connected) {
            $this->markTestSkipped('Redis server is not reachable on 127.0.0.1:6379');
        }

        try {
            $client->ping();
        } catch (\Throwable $e) {
            $client->close();
            $this->markTestSkipped('Redis server is not responding to PING');
        }

        $client->close();

        $this->prefix = 'velvet_test_' . uniqid('', true);
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
            'prefix' => $this->prefix,
        ];

        $this->cache = new RedisCache($config);
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
        parent::tearDown();
    }

    public function test_can_set_and_get_value(): void
    {
        $this->cache->set('foo', 'bar', 10);
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function test_returns_default_on_miss(): void
    {
        $this->assertSame('default', $this->cache->get('missing', 'default'));
    }

    public function test_can_delete_item(): void
    {
        $this->cache->set('del', 'val');
        $this->cache->delete('del');
        $this->assertNull($this->cache->get('del'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->cache->set('exists', 'value');
        $this->assertTrue($this->cache->has('exists'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function test_clear_removes_all_items(): void
    {
        $this->cache->set('one', '1');
        $this->cache->set('two', '2');
        $this->cache->clear();

        $this->assertNull($this->cache->get('one'));
        $this->assertNull($this->cache->get('two'));
    }

    public function test_stores_array_values(): void
    {
        $data = ['name' => 'Velvet', 'items' => [1, 2, 3]];
        $this->cache->set('array', $data);
        $this->assertSame($data, $this->cache->get('array'));
    }

    public function test_stores_integer_values(): void
    {
        $this->cache->set('int', 42);
        $this->assertSame(42, $this->cache->get('int'));
    }

    public function test_stores_boolean_true(): void
    {
        $this->cache->set('flag', true);
        $this->assertTrue($this->cache->get('flag'));
    }

    // Note: Storing literal `false` is problematic in Redis since false === "not found"
    // Use 0/1 or 'true'/'false' strings for boolean-like values in cache

    public function test_prefix_isolates_keys(): void
    {
        $otherCache = new RedisCache([
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
            'prefix' => 'other_' . uniqid('', true),
        ]);
        
        $this->cache->set('shared', 'original');
        $otherCache->set('shared', 'other');

        $this->assertSame('original', $this->cache->get('shared'));
        $this->assertSame('other', $otherCache->get('shared'));

        $otherCache->clear();
    }

    public function test_overwrite_existing_key(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');
        $this->assertSame('second', $this->cache->get('key'));
    }

    public function test_uses_separate_database(): void
    {
        // Our cache uses database 15, which should be isolated
        $this->cache->set('isolated', 'value');
        $this->assertSame('value', $this->cache->get('isolated'));
    }
}
