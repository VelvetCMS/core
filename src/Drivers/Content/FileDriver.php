<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Content;

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Database\Collection;
use VelvetCMS\Exceptions\NotFoundException;
use VelvetCMS\Exceptions\ValidationException;
use VelvetCMS\Models\Page;
use VelvetCMS\Services\ContentParser;

class FileDriver implements ContentDriver
{
    private string $contentPath;
    private string $indexPath;
    /** @var array{pages: array<string, array>} */
    private array $index = ['pages' => []];
    private bool $indexLoaded = false;
    private bool $indexDirty = false;

    public function __construct(
        private readonly ContentParser $parser,
        ?string $contentPath = null
    ) {
        $this->contentPath = $contentPath ?? content_path('pages');
        $this->indexPath = storage_path('cache/file-driver-index.json');
        $this->loadIndex();

        if (!is_dir($this->contentPath)) {
            mkdir($this->contentPath, 0755, true);
        }
    }

    public function load(string $slug): Page
    {
        $this->ensureIndex();

        $filepath = $this->getFilePath($slug);

        if (!file_exists($filepath)) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        $entry = $this->index['pages'][$slug] ?? null;
        $mtime = filemtime($filepath) ?: 0;

        if ($entry === null || ($entry['mtime'] ?? 0) !== $mtime) {
            $entry = $this->createIndexEntry($slug, $filepath, $mtime);
            $this->index['pages'][$slug] = $entry;
            $this->indexDirty = true;
            $this->saveIndex();
        }

        return $this->makePageFromEntry($entry);
    }

    public function save(Page $page): bool
    {
        $this->validatePage($page);

        $filepath = $this->getFilePath($page->slug);

        $content = $this->buildFileContent($page);

        $result = file_put_contents($filepath, $content) !== false;

        if ($result) {
            $this->ensureIndex();
            $mtime = filemtime($filepath) ?: time();
            $this->index['pages'][$page->slug] = $this->createIndexEntry($page->slug, $filepath, $mtime);
            $this->indexDirty = true;
            $this->saveIndex();
        }

        return $result;
    }

    public function list(array $filters = []): Collection
    {
        $this->ensureIndex();

        $pages = [];

        foreach ($this->index['pages'] as $entry) {
            $pageData = $entry['page'] ?? [];

            if (isset($filters['status']) && ($pageData['status'] ?? 'draft') !== $filters['status']) {
                continue;
            }

            $pages[] = $this->makePageFromEntry($entry);
        }

        usort($pages, fn ($a, $b) => $b->createdAt <=> $a->createdAt);

        if (isset($filters['offset'])) {
            $pages = array_slice($pages, (int) $filters['offset']);
        }

        if (isset($filters['limit'])) {
            $pages = array_slice($pages, 0, (int) $filters['limit']);
        }

        return new Collection($pages);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        $this->ensureIndex();

        $pages = [];

        foreach ($this->index['pages'] as $entry) {
            $pageData = $entry['page'] ?? [];

            if (isset($filters['status']) && ($pageData['status'] ?? 'draft') !== $filters['status']) {
                continue;
            }

            $pages[] = $this->makePageFromEntry($entry);
        }

        usort($pages, fn ($a, $b) => $b->createdAt <=> $a->createdAt);

        // Slice for pagination
        $slice = array_slice($pages, ($page - 1) * $perPage, $perPage);

        return new Collection($slice);
    }

    public function delete(string $slug): bool
    {
        $filepath = $this->getFilePath($slug);

        if (!file_exists($filepath)) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        $deleted = unlink($filepath);

        if ($deleted) {
            $this->ensureIndex();
            if (isset($this->index['pages'][$slug])) {
                unset($this->index['pages'][$slug]);
                $this->indexDirty = true;
                $this->saveIndex();
            }
        }

        return $deleted;
    }

    public function exists(string $slug): bool
    {
        $this->ensureIndex();

        if (isset($this->index['pages'][$slug])) {
            return true;
        }

        return file_exists($this->getFilePath($slug));
    }

    public function count(array $filters = []): int
    {
        $this->ensureIndex();

        if ($filters === []) {
            return count($this->index['pages']);
        }

        return $this->list($filters)->count();
    }

    private function getFilePath(string $slug): string
    {
        $safeSlug = sanitize_slug($slug);
        if ($safeSlug === '') {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        // Check for .vlt first, then .md
        $vltPath = $this->contentPath . '/' . $safeSlug . '.vlt';
        if (file_exists($vltPath)) {
            return $vltPath;
        }
        return $this->contentPath . '/' . $safeSlug . '.md';
    }

    private function buildFileContent(Page $page): string
    {
        $frontmatter = [
            'title' => $page->title,
            'status' => $page->status,
        ];

        if ($page->layout) {
            $frontmatter['layout'] = $page->layout;
        }

        if ($page->excerpt) {
            $frontmatter['excerpt'] = $page->excerpt;
        }

        if ($page->createdAt) {
            $frontmatter['created_at'] = $page->createdAt->format('Y-m-d H:i:s');
        }

        if ($page->updatedAt) {
            $frontmatter['updated_at'] = $page->updatedAt->format('Y-m-d H:i:s');
        }

        if ($page->publishedAt) {
            $frontmatter['published_at'] = $page->publishedAt->format('Y-m-d H:i:s');
        }

        // Add custom meta
        foreach ($page->meta as $key => $value) {
            $frontmatter[$key] = $value;
        }

        // Use Symfony YAML dumper for proper formatting
        $yaml = "---\n";
        $yaml .= \Symfony\Component\Yaml\Yaml::dump($frontmatter, inline: 2, indent: 2);
        $yaml .= "---\n\n";

        return $yaml . $page->content;
    }

    private function loadIndex(): void
    {
        if ($this->indexLoaded) {
            return;
        }

        if (!file_exists($this->indexPath)) {
            $this->index = ['pages' => []];
            $this->indexLoaded = true;
            $this->indexDirty = true;
            return;
        }

        $contents = file_get_contents($this->indexPath);

        if ($contents === false) {
            $this->index = ['pages' => []];
            $this->indexLoaded = true;
            return;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded) && isset($decoded['pages']) && is_array($decoded['pages'])) {
                $this->index = $decoded;
            }
        } catch (\JsonException $e) {
            $this->index = ['pages' => []];
        }

        $this->indexLoaded = true;
    }

    private function saveIndex(): void
    {
        if (!$this->indexDirty) {
            return;
        }

        $directory = dirname($this->indexPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->indexPath,
            json_encode($this->index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        $this->indexDirty = false;
    }

    private function ensureIndex(): void
    {
        if ($this->indexLoaded && !$this->indexDirty) {
            return;
        }

        // Scan for both .md and .vlt files
        $mdFiles = glob($this->contentPath . '/*.md') ?: [];
        $vltFiles = glob($this->contentPath . '/*.vlt') ?: [];
        $files = array_merge($mdFiles, $vltFiles);

        $seen = [];

        foreach ($files as $file) {
            // Determine extension
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $slug = basename($file, '.' . $ext);

            $seen[] = $slug;
            $mtime = filemtime($file) ?: 0;

            $entry = $this->index['pages'][$slug] ?? null;

            if ($entry === null || ($entry['mtime'] ?? 0) !== $mtime) {
                $this->index['pages'][$slug] = $this->createIndexEntry($slug, $file, $mtime);
                $this->indexDirty = true;
            }
        }

        foreach (array_keys($this->index['pages']) as $slug) {
            if (!in_array($slug, $seen, true)) {
                unset($this->index['pages'][$slug]);
                $this->indexDirty = true;
            }
        }

        $this->saveIndex();
    }

    private function createIndexEntry(string $slug, string $filepath, int $mtime): array
    {
        $content = file_get_contents($filepath) ?: '';
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $format = ($extension === 'md') ? 'markdown' : 'auto';
        $parsed = $this->parser->parse($content, $format);

        $frontmatter = $parsed['frontmatter'];
        $html = $parsed['html'];
        $body = $parsed['body'];
        $standardFields = ['title', 'status', 'layout', 'excerpt', 'created_at', 'updated_at', 'published_at'];

        $status = $frontmatter['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $title = $frontmatter['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug));

        $pageData = [
            'slug' => $slug,
            'content' => $body,
            'status' => $status,
            'title' => $title,
        ];

        foreach (['layout', 'excerpt', 'created_at', 'updated_at', 'published_at'] as $field) {
            if (isset($frontmatter[$field])) {
                $pageData[$field] = $frontmatter[$field];
            }
        }

        $meta = [];
        foreach ($frontmatter as $key => $value) {
            if (!in_array($key, $standardFields, true)) {
                $meta[$key] = $value;
            }
        }

        $pageData['meta'] = $meta;

        return [
            'slug' => $slug,
            'path' => $filepath,
            'mtime' => $mtime,
            'page' => $pageData,
            'html' => $html,
        ];
    }

    private function makePageFromEntry(array $entry): Page
    {
        $page = Page::fromArray($entry['page']);
        $page->setHtml($entry['html']);
        return $page;
    }

    private function validatePage(Page $page): void
    {
        $errors = [];

        if (empty($page->slug)) {
            $errors['slug'] = 'Slug is required';
        } elseif (sanitize_slug($page->slug) !== $page->slug) {
            $errors['slug'] = 'Slug contains invalid characters';
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
