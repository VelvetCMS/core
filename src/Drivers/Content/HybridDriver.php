<?php

declare(strict_types=1);

namespace VelvetCMS\Drivers\Content;

use VelvetCMS\Contracts\ContentDriver;
use VelvetCMS\Models\Page;
use VelvetCMS\Database\Collection;
use VelvetCMS\Database\Connection;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Exceptions\NotFoundException;
use VelvetCMS\Exceptions\ValidationException;

class HybridDriver implements ContentDriver
{
    private string $contentPath;
    
    public function __construct(
        private readonly ContentParser $parser,
        private readonly Connection $db,
        ?string $contentPath = null
    ) {
        $this->contentPath = $contentPath ?? content_path('pages');
        
        if (!is_dir($this->contentPath)) {
            mkdir($this->contentPath, 0755, true);
        }
    }
    
    public function load(string $slug): Page
    {
        $data = $this->db->table('pages')
            ->where('slug', '=', $slug)
            ->first();
        
        if (!$data) {
            throw new NotFoundException("Page '{$slug}' not found");
        }
        
        // Content priority: File > DB Fallback
        $filepath = $this->getFilePath($slug);
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
        } else {
            $content = $data['content'] ?? '';
        }
        
        if (isset($data['meta']) && is_string($data['meta'])) {
            $data['meta'] = json_decode($data['meta'], true) ?? [];
        }
        
        $data['content'] = $content;
        $page = Page::fromArray($data);
        
        $parsed = $this->parser->parse($content, 'markdown');
        $page->setHtml($parsed['html']);
        
        return $page;
    }
    
    public function save(Page $page): bool
    {
        $this->validatePage($page);
        
        $filepath = $this->getFilePath($page->slug);
        file_put_contents($filepath, $page->content);
        
        $data = [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => '', // Don't store in DB, use file
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
        
        // Convert to Page objects and load content from files
        $pages = [];
        foreach ($results as $data) {
            // Load content from file
            $filepath = $this->getFilePath($data['slug']);
            if (file_exists($filepath)) {
                $data['content'] = file_get_contents($filepath);
            }
            
            // Decode JSON meta
            if (isset($data['meta']) && is_string($data['meta'])) {
                $data['meta'] = json_decode($data['meta'], true) ?? [];
            }
            
            $page = Page::fromArray($data);
            
            // Parse content if exists
            if ($page->content) {
                $parsed = $this->parser->parse($page->content, 'markdown');
                $page->setHtml($parsed['html']);
            }
            
            $pages[] = $page;
        }
        
        return new Collection($pages);
    }
    
    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        $offset = ($page - 1) * $perPage;
        $filters['limit'] = $perPage;
        $filters['offset'] = $offset;
        
        return $this->list($filters);
    }

    public function delete(string $slug): bool
    {
        if (!$this->exists($slug)) {
            throw new NotFoundException("Page '{$slug}' not found");
        }
        
        // Delete file
        $filepath = $this->getFilePath($slug);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // Delete from database
        $this->db->table('pages')
            ->where('slug', '=', $slug)
            ->delete();
        
        return true;
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
    
    private function getFilePath(string $slug): string
    {
        $safeSlug = sanitize_slug($slug);
        if ($safeSlug === '') {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        return $this->contentPath . '/' . $safeSlug . '.md';
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
