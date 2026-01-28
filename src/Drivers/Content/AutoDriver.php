<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Content;

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Database\Collection;
use VelvetCMS\Models\Page;

class AutoDriver implements ContentDriver
{
    private ContentDriver $activeDriver;
    private string $activeDriverName;
    private int $threshold;
    private bool $overThreshold = false;

    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly HybridDriver $hybridDriver,
        private readonly ?DBDriver $dbDriver = null,
        ?int $threshold = null
    ) {
        $this->threshold = $threshold ?? config('content.drivers.auto.threshold', 100);
        $this->selectDriver();
    }

    /**
     * Select driver once at boot based on configured preference and page count.
     */
    private function selectDriver(): void
    {
        $smallDriver = config('content.drivers.auto.small_site', 'file');
        $largeDriver = config('content.drivers.auto.large_site', 'hybrid');

        $pageCount = $this->fileDriver->count();
        $this->overThreshold = $pageCount >= $this->threshold;

        // Use configured driver preference
        $preferredDriver = $this->overThreshold ? $largeDriver : $smallDriver;
        $this->activeDriver = $this->resolveDriver($preferredDriver);
        $this->activeDriverName = $preferredDriver;

        // Warn if over threshold but using file driver
        if ($this->overThreshold && $this->activeDriverName === 'file') {
            $this->logThresholdWarning($pageCount);
        }
    }

    private function resolveDriver(string $name): ContentDriver
    {
        return match ($name) {
            'file' => $this->fileDriver,
            'hybrid' => $this->hybridDriver,
            'db' => $this->dbDriver ?? $this->hybridDriver,
            default => $this->fileDriver,
        };
    }

    private function logThresholdWarning(int $pageCount): void
    {
        $logger = app('logger');
        $logger?->warning(
            "AutoDriver: {$pageCount} pages exceeds threshold ({$this->threshold}). " .
            "Consider migrating: ./velvet content:migrate hybrid"
        );
    }

    public function getActiveDriver(): ContentDriver
    {
        return $this->activeDriver;
    }

    public function getActiveDriverName(): string
    {
        return $this->activeDriverName;
    }

    public function isOverThreshold(): bool
    {
        return $this->overThreshold;
    }

    public function load(string $slug): Page
    {
        return $this->activeDriver->load($slug);
    }

    public function save(Page $page): bool
    {
        return $this->activeDriver->save($page);
    }

    public function list(array $filters = []): Collection
    {
        return $this->activeDriver->list($filters);
    }

    public function delete(string $slug): bool
    {
        return $this->activeDriver->delete($slug);
    }

    public function exists(string $slug): bool
    {
        return $this->activeDriver->exists($slug);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        return $this->activeDriver->paginate($page, $perPage, $filters);
    }

    public function count(array $filters = []): int
    {
        return $this->activeDriver->count($filters);
    }
}
