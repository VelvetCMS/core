<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\External\Services\Parsers;

use PHPUnit\Framework\Attributes\Group;
use VelvetCMS\Services\Parsers\ParsedownParser;
use VelvetCMS\Services\Parsers\ParserFactory;
use VelvetCMS\Tests\Support\TestCase;

#[Group('external')]
final class ParsedownParserFactoryTest extends TestCase
{
    public function test_creates_parsedown_parser_when_package_is_installed(): void
    {
        if (!class_exists('Parsedown')) {
            $this->markTestSkipped('Parsedown not installed.');
        }

        $parser = (new ParserFactory())->make('parsedown');

        $this->assertInstanceOf(ParsedownParser::class, $parser);
    }
}
