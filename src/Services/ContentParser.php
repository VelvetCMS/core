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
        $buffer = [];
        $html = '';

        foreach ($lines as $line) {
            if (preg_match('/^@([a-z]+)\s*$/i', $line, $m)) {
                $html .= $this->processBlock($type, implode("\n", $buffer));
                $type = strtolower($m[1]);
                $buffer = [];
            } else {
                $buffer[] = $line;
            }
        }

        $html .= $this->processBlock($type, implode("\n", $buffer));
        return $html;
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
