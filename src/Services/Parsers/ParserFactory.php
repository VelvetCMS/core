<?php

declare(strict_types=1);

namespace VelvetCMS\Services\Parsers;

use InvalidArgumentException;
use RuntimeException;
use VelvetCMS\Contracts\ParserInterface;

class ParserFactory
{
    public function make(string $driver, array $config = []): ParserInterface
    {
        return match ($driver) {
            'commonmark' => $this->createCommonMark($config),
            'parsedown' => new ParsedownParser($config),
            'html', 'none' => new HtmlParser(),
            default => throw new InvalidArgumentException("Unsupported parser driver: {$driver}"),
        };
    }

    private function createCommonMark(array $config): CommonMarkParser
    {
        if (!class_exists(\League\CommonMark\MarkdownConverter::class)) {
            throw new RuntimeException(
                "The 'commonmark' driver requires the 'league/commonmark' package.\n" .
                'Please run: composer require league/commonmark'
            );
        }

        return new CommonMarkParser($config);
    }
}
