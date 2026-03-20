<?php

declare(strict_types=1);

namespace VelvetCMS\Content\Index;

use Symfony\Component\Yaml\Yaml;

final class PageIndexer
{
    public function indexFile(string $slug, string $filepath, ?int $mtime = null): PageIndexEntry
    {
        $content = file_get_contents($filepath) ?: '';
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $format = $extension === 'md' ? 'markdown' : 'auto';
        $frontmatter = $this->extractFrontmatter($content);

        $status = $frontmatter['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $title = $frontmatter['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
        $standardFields = ['title', 'status', 'layout', 'excerpt', 'trusted', 'created_at', 'updated_at', 'published_at'];
        $meta = [];

        foreach ($frontmatter as $key => $value) {
            if (!in_array($key, $standardFields, true)) {
                $meta[$key] = $value;
            }
        }

        return new PageIndexEntry(
            slug: $slug,
            path: $filepath,
            mtime: $mtime ?? (filemtime($filepath) ?: 0),
            format: $format,
            title: $title,
            status: $status,
            layout: isset($frontmatter['layout']) ? (string) $frontmatter['layout'] : null,
            excerpt: isset($frontmatter['excerpt']) ? (string) $frontmatter['excerpt'] : null,
            trusted: (bool) ($frontmatter['trusted'] ?? false),
            createdAt: isset($frontmatter['created_at']) ? (string) $frontmatter['created_at'] : null,
            updatedAt: isset($frontmatter['updated_at']) ? (string) $frontmatter['updated_at'] : null,
            publishedAt: isset($frontmatter['published_at']) ? (string) $frontmatter['published_at'] : null,
            meta: $meta,
        );
    }

    private function extractFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $m)) {
            return [];
        }

        try {
            $parsed = Yaml::parse($m[1]);
            return is_array($parsed) ? $parsed : [];
        } catch (\Exception) {
            return [];
        }
    }
}
