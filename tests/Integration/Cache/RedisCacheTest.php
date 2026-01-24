<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Cache;

use VelvetCMS\Drivers\Cache\RedisCache;
use VelvetCMS\Tests\Support\TestCase;

final class RedisCacheTest extends TestCase
{
    private ?RedisCache $cache = null;

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

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 15,
            'prefix' => 'velvet_test_' . uniqid('', true),
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

    public function testBasicSetGetDelete(): void
    {
        $this->cache?->set('foo', 'bar', 10);
        $this->assertSame('bar', $this->cache?->get('foo'));

        $this->cache?->delete('foo');
        $this->assertNull($this->cache?->get('foo'));
    }
}
