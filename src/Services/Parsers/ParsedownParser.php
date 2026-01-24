<?php

declare(strict_types=1);

namespace VelvetCMS\Services\Parsers;

use VelvetCMS\Contracts\ParserInterface;
use RuntimeException;

class ParsedownParser implements ParserInterface
{
    private \Parsedown $parser;

    public function __construct(array $config = [])
    {
        if (!class_exists('Parsedown')) {
            throw new RuntimeException(
                "The 'parsedown' driver requires the 'erusev/parsedown' package.\n" .
                "Please run: composer require erusev/parsedown"
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
