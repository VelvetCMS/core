<?php

declare(strict_types=1);

namespace VelvetCMS\Services\Parsers;

use RuntimeException;
use VelvetCMS\Contracts\ParserInterface;

class ParsedownParser implements ParserInterface
{
    /** @var object */
    private object $parser;

    public function __construct(array $config = [])
    {
        if (!class_exists('Parsedown')) {
            throw new RuntimeException(
                "The 'parsedown' driver requires the 'erusev/parsedown' package.\n" .
                'Please run: composer require erusev/parsedown'
            );
        }

        $this->parser = new \Parsedown();

        if (isset($config['html_input']) && $config['html_input'] === 'strip') {
            $this->parser->setSafeMode(true);
        }

        $this->parser->setBreaksEnabled($config['breaks'] ?? true);
    }

    public function parse(string $content): string
    {
        return (string) $this->parser->text($content);
    }
}
