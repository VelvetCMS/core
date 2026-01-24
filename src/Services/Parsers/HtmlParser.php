<?php

declare(strict_types=1);

namespace VelvetCMS\Services\Parsers;

use VelvetCMS\Contracts\ParserInterface;

class HtmlParser implements ParserInterface
{
    public function parse(string $content): string
    {
        return $content;
    }
}
