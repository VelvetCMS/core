<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

interface ParserInterface
{
    public function parse(string $content): string;
}
