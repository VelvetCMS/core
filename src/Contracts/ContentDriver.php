<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

use VelvetCMS\Database\Collection;
use VelvetCMS\Models\Page;

interface ContentDriver
{
    /** @throws \VelvetCMS\Exceptions\NotFoundException */
    public function load(string $slug): Page;

    /** @throws \VelvetCMS\Exceptions\ValidationException */
    public function save(Page $page): bool;

    public function list(array $filters = []): Collection;

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection;

    /** @throws \VelvetCMS\Exceptions\NotFoundException */
    public function delete(string $slug): bool;

    public function exists(string $slug): bool;

    public function count(array $filters = []): int;

    /** Return the last-modified timestamp for a page, or null if it does not exist. */
    public function lastModified(string $slug): ?int;
}
