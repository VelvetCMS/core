<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Cache;

use VelvetCMS\Drivers\Cache\ApcuCache;
use VelvetCMS\Tests\Support\TestCase;

final class ApcuCacheTest extends TestCase
{
    private ?ApcuCache $cache = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('apcu') || !ini_get('apc.enabled')) {
            $this->markTestSkipped('APCu extension is not loaded or enabled.');
        }

        // In CLI, apc.enable_cli must be 1
        if (PHP_SAPI === 'cli' && !ini_get('apc.enable_cli')) {
             $this->markTestSkipped('apc.enable_cli must be enabled for testing.');
        }

        $this->cache = new ApcuCache(['prefix' => 'test_' . uniqid()]);
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        if ($this->cache) {
            $this->cache->clear();
        }
        parent::tearDown();
    }

    public function test_can_store_and_retrieve_values(): void
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
}
