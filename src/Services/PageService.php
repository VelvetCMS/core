<?php

declare(strict_types=1);

namespace VelvetCMS\Services;

use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Core\EventDispatcher;
use VelvetCMS\Database\Collection;
use VelvetCMS\Models\Page;
use VelvetCMS\Support\Cache\CacheTagManager;

class PageService
{
    public function __construct(
        private readonly ContentDriver $driver,
        private readonly EventDispatcher $events,
        private readonly CacheDriver $cache,
        private readonly CacheTagManager $cacheTags
    ) {
    }

    public function load(string $slug): Page
    {
        return $this->cache->remember("page:$slug", 300, function () use ($slug) {
            $this->events->dispatch('page.loading', $slug);
            $page = $this->driver->load($slug);
            $this->events->dispatch('page.loaded', $page);
            return $page;
        });
    }

    public function save(Page $page): bool
    {
        $this->events->dispatch('page.saving', $page);
        if ($page->createdAt === null) {
            $page->createdAt = new \DateTime();
        }
        $page->updatedAt = new \DateTime();
        $result = $this->driver->save($page);

        $this->cache->delete("page:{$page->slug}");
        $this->cacheTags->flush('pages:list');

        $this->events->dispatch('page.saved', $page);
        return $result;
    }

    public function list(array $filters = []): Collection
    {
        $cacheKey = $this->makeListCacheKey($filters);

        return $this->cacheTags->remember('pages:list', $cacheKey, 300, function () use ($filters) {
            return $this->driver->list($filters);
        });
    }

    public function delete(string $slug): bool
    {
        $this->events->dispatch('page.deleting', $slug);
        $result = $this->driver->delete($slug);

        $this->cache->delete("page:$slug");
        $this->cacheTags->flush('pages:list');

        $this->events->dispatch('page.deleted', $slug);
        return $result;
    }

    public function exists(string $slug): bool
    {
        return $this->driver->exists($slug);
    }

    public function published(): Collection
    {
        return $this->list(['status' => 'published']);
    }

    public function drafts(): Collection
    {
        return $this->list(['status' => 'draft']);
    }

    public function count(): int
    {
        return $this->list()->count();
    }

    public function recent(int $limit = 5): Collection
    {
        return $this->list([
            'order_by' => 'updated_at',
            'order' => 'desc',
            'limit' => $limit,
        ]);
    }

    private function makeListCacheKey(array $filters): string
    {
        if ($filters === []) {
            return 'pages:list:all';
        }

        ksort($filters);

        return 'pages:list:' . md5(serialize($filters));
    }
}
