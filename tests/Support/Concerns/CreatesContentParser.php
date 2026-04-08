<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Concerns;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\ConfigRepository;
use VelvetCMS\Services\ContentParser;
use VelvetCMS\Services\Parsers\CommonMarkParser;

/**
 * Factory for building a ContentParser backed by a temp FileCache.
 *
 * Requires the using class to provide `makeFileCache()` (from TestCase).
 */
trait CreatesContentParser
{
    protected function makeContentParser(array $commonMarkOptions = []): ContentParser
    {
        return new ContentParser(
            $this->makeFileCache(),
            new CommonMarkParser($commonMarkOptions),
            Application::getInstance()->make(ConfigRepository::class),
        );
    }
}
