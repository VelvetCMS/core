<?php

declare(strict_types=1);

namespace VelvetCMS\Services;

use Symfony\Component\Yaml\Yaml;
use VelvetCMS\Contracts\CacheDriver;
use VelvetCMS\Contracts\ParserInterface;

/**
 * Unified content parser for Markdown and Velvet (.vlt) documents.
 * Handles frontmatter extraction and mixed content blocks.
 */
class ContentParser
{
    public function __construct(
        private readonly CacheDriver $cache,
        private readonly ParserInterface $parser
    ) {
    }

    /**
     * @return array{frontmatter: array, html: string, body: string}
     */
    public function parse(string $content, string $format = 'auto'): array
    {
        $parts = $this->extractFrontmatter($content);
        $body = $parts['body'];

        if ($format === 'markdown') {
            $html = $this->markdown($body);
        } else {
            $html = $this->parseBlocks($body);
        }

        return [
            'frontmatter' => $parts['frontmatter'],
            'html' => $html,
            'body' => $body,
        ];
    }

    public function markdown(string $content, bool $useCache = true): string
    {
        if (!$useCache) {
            return $this->parser->parse($content);
        }

        $key = 'md:' . md5($content);
        $ttl = (int) config('content.parser.cache_ttl', 600);

        return $this->cache->remember($key, $ttl, fn () => $this->parser->parse($content));
    }

    /**
     * @return array{frontmatter: array, body: string}
     */
    public function extractFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $m)) {
            return ['frontmatter' => [], 'body' => $content];
        }

        try {
            $frontmatter = Yaml::parse($m[1]) ?? [];
        } catch (\Exception) {
            $frontmatter = [];
        }

        return ['frontmatter' => $frontmatter, 'body' => $m[2]];
    }

    private function parseBlocks(string $content): string
    {
        $lines = explode("\n", $content);
        $type = 'markdown';
        $componentName = null;
        $buffer = [];
        $html = '';

        foreach ($lines as $line) {
            // Match @component('name') opening
            if (preg_match("/^@component\(['\"]([a-zA-Z0-9._-]+)['\"]\)\s*$/i", $line, $m)) {
                // Flush current buffer
                $html .= $this->processBlock($type, implode("\n", $buffer));
                $type = 'component';
                $componentName = $m[1];
                $buffer = [];
                continue;
            }

            // Match @endcomponent closing
            if ($type === 'component' && preg_match('/^@endcomponent\s*$/i', $line)) {
                $html .= $this->processComponent($componentName, implode("\n", $buffer));
                $type = 'markdown';
                $componentName = null;
                $buffer = [];
                continue;
            }

            // Match regular block directives (@html, @markdown, @md, @text)
            if ($type !== 'component' && preg_match('/^@([a-z]+)\s*$/i', $line, $m)) {
                $html .= $this->processBlock($type, implode("\n", $buffer));
                $type = strtolower($m[1]);
                $buffer = [];
                continue;
            }

            $buffer[] = $line;
        }

        $html .= $this->processBlock($type, implode("\n", $buffer));
        return $html;
    }

    /**
     * Transform @component YAML block into an @include directive string.
     * ViewEngine resolves it at render time — no wiring needed.
     */
    private function processComponent(string $name, string $content): string
    {
        $viewName = str_contains($name, '.') ? $name : 'components.' . $name;

        $props = [];
        $trimmed = trim($content);
        if ($trimmed !== '') {
            try {
                $parsed = Yaml::parse($trimmed);
                if (is_array($parsed)) {
                    $props = $parsed;
                }
            } catch (\Exception) {
                $props = ['content' => $trimmed];
            }
        }

        $exported = var_export($props, true);
        return "@include('{$viewName}', {$exported})\n";
    }

    private function processBlock(string $type, string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        return match ($type) {
            'markdown', 'md' => $this->markdown($content, false),
            'html' => $content . "\n",
            'text' => htmlspecialchars($content, ENT_QUOTES) . "\n",
            default => $content . "\n",
        };
    }
}
