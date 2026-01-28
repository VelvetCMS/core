<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Content;

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Database\Collection;
use VelvetCMS\Models\Page;

class AutoDriver implements ContentDriver
{
    private ContentDriver $activeDriver;
    private int $threshold;
    private ?int $lastKnownCount = null;
    private bool $forceNextEvaluation = false;

    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly HybridDriver $hybridDriver,
        ?int $threshold = null
    ) {
        $this->threshold = $threshold ?? config('content.drivers.auto.threshold', 100);
        $this->determineActiveDriver();
    }

    private function determineActiveDriver(bool $force = false): void
    {
        if ($this->lastKnownCount !== null && !$force && !$this->forceNextEvaluation) {
            $pageCount = $this->lastKnownCount;
        } else {
            $pageCount = $this->countPages();
            $this->lastKnownCount = $pageCount;
            $this->forceNextEvaluation = false;
        }

        if ($pageCount >= $this->threshold) {
            // Switch to hybrid for better performance with many pages
            $this->activeDriver = $this->hybridDriver;
        } else {
            // Use file driver for simplicity with few pages
            $this->activeDriver = $this->fileDriver;
        }
    }

    private function countPages(): int
    {
        // Always use file driver count as the source of truth
        // since FileDriver is the starting point and Hybrid also manages files
        return $this->fileDriver->count();
    }

    public function getActiveDriver(): ContentDriver
    {
        return $this->activeDriver;
    }

    public function getActiveDriverName(): string
    {
        return match (true) {
            $this->activeDriver instanceof FileDriver => 'file',
            $this->activeDriver instanceof HybridDriver => 'hybrid',
            default => 'unknown',
        };
    }

    public function load(string $slug): Page
    {
        return $this->activeDriver->load($slug);
    }

    public function save(Page $page): bool
    {
        $result = $this->activeDriver->save($page);
        $this->forceNextEvaluation = true;

        $this->determineActiveDriver();

        return $result;
    }

    public function list(array $filters = []): Collection
    {
        $collection = $this->activeDriver->list($filters);

        if ($filters === []) {
            $this->forceNextEvaluation = false;
            $this->lastKnownCount = $collection->count();
        }

        return $collection;
    }

    public function delete(string $slug): bool
    {
        $result = $this->activeDriver->delete($slug);
        $this->forceNextEvaluation = true;

        $this->determineActiveDriver();

        return $result;
    }

    public function exists(string $slug): bool
    {
        return $this->activeDriver->exists($slug);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        return $this->activeDriver->paginate($page, $perPage, $filters);
    }

    /**
     * Count pages
     */
    public function count(array $filters = []): int
    {
        if ($filters === []) {
            // Get fresh count from the active driver
            $count = $this->activeDriver->count();
            $this->lastKnownCount = $count;
            return $count;
        }

        return $this->activeDriver->count($filters);
    }
}
