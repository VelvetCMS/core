<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Services\Parsers;

use VelvetCMS\Services\Parsers\CommonMarkParser;
use VelvetCMS\Services\Parsers\HtmlParser;
use VelvetCMS\Services\Parsers\ParserFactory;
use VelvetCMS\Tests\Support\TestCase;

final class ParserFactoryTest extends TestCase
{
    private ParserFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ParserFactory();
    }

    public function test_creates_commonmark_parser(): void
    {
        $parser = $this->factory->make('commonmark');
        $this->assertInstanceOf(CommonMarkParser::class, $parser);
    }

    public function test_creates_html_parser(): void
    {
        $parser = $this->factory->make('html');
        $this->assertInstanceOf(HtmlParser::class, $parser);
    }

    public function test_creates_html_parser_for_none(): void
    {
        $parser = $this->factory->make('none');
        $this->assertInstanceOf(HtmlParser::class, $parser);
    }

    public function test_throws_exception_for_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->make('invalid-driver');
    }
}
