<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Content;

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Database\Collection;
use VelvetCMS\Database\Connection;
use VelvetCMS\Exceptions\NotFoundException;
use VelvetCMS\Exceptions\ValidationException;
use VelvetCMS\Models\Page;

class DBDriver implements ContentDriver
{
    public function __construct(
        private readonly Connection $db
    ) {
    }

    public function load(string $slug): Page
    {
        $data = $this->db->table('pages')
            ->where('slug', '=', $slug)
            ->first();

        if (!$data) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        if (isset($data['meta']) && is_string($data['meta'])) {
            $data['meta'] = json_decode($data['meta'], true) ?? [];
        }

        return Page::fromArray($data);
    }

    public function save(Page $page): bool
    {
        $this->validatePage($page);

        $data = [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'status' => $page->status,
            'layout' => $page->layout,
            'excerpt' => $page->excerpt,
            'meta' => json_encode($page->meta),
            'created_at' => $page->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $page->updatedAt?->format('Y-m-d H:i:s'),
            'published_at' => $page->publishedAt?->format('Y-m-d H:i:s'),
        ];

        if ($this->exists($page->slug)) {
            $this->db->table('pages')
                ->where('slug', '=', $page->slug)
                ->update($data);
        } else {
            $this->db->table('pages')->insert($data);
        }

        return true;
    }

    public function list(array $filters = []): Collection
    {
        $query = $this->db->table('pages');

        if (isset($filters['status'])) {
            $query->where('status', '=', $filters['status']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $query->orderBy($orderBy, $orderDir);

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        if (isset($filters['offset'])) {
            $query->offset((int) $filters['offset']);
        }

        $results = $query->get();

        // Convert to Page objects
        $pages = [];
        foreach ($results as $data) {
            // Decode JSON meta
            if (isset($data['meta']) && is_string($data['meta'])) {
                $data['meta'] = json_decode($data['meta'], true) ?? [];
            }

            $pages[] = Page::fromArray($data);
        }

        return new Collection($pages);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        $query = $this->db->table('pages');

        if (isset($filters['status'])) {
            $query->where('status', '=', $filters['status']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $query->orderBy($orderBy, $orderDir);

        $query->limit($perPage);
        $query->offset(($page - 1) * $perPage);

        $results = $query->get();

        // Convert to Page objects
        $pages = [];
        foreach ($results as $data) {
            // Decode JSON meta
            if (isset($data['meta']) && is_string($data['meta'])) {
                $data['meta'] = json_decode($data['meta'], true) ?? [];
            }

            $pages[] = Page::fromArray($data);
        }

        return new Collection($pages);
    }

    public function delete(string $slug): bool
    {
        if (!$this->exists($slug)) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        $affected = $this->db->table('pages')
            ->where('slug', '=', $slug)
            ->delete();

        return $affected > 0;
    }

    public function exists(string $slug): bool
    {
        $result = $this->db->table('pages')
            ->where('slug', '=', $slug)
            ->first();

        return $result !== null;
    }

    public function count(array $filters = []): int
    {
        $query = $this->db->table('pages');

        if (isset($filters['status'])) {
            $query->where('status', '=', $filters['status']);
        }

        return $query->count();
    }

    private function validatePage(Page $page): void
    {
        $errors = [];

        if (empty($page->slug)) {
            $errors['slug'] = 'Slug is required';
        }

        if (empty($page->title)) {
            $errors['title'] = 'Title is required';
        }

        if (!in_array($page->status, ['draft', 'published'])) {
            $errors['status'] = 'Invalid status';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
