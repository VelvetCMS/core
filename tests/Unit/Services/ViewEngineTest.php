<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Services;

use VelvetCMS\Services\ViewEngine;
use VelvetCMS\Tests\Support\TestCase;

final class ViewEngineTest extends TestCase
{
    public function test_renders_simple_view(): void
    {
        $viewDir = $this->tmpDir . '/views';
        $this->mkdir($viewDir . '/partials');

        file_put_contents($viewDir . '/hello.velvet.php', 'Hello, {{ $name }}');

        $engine = new ViewEngine($viewDir, $this->tmpDir . '/cache/views');
        $output = $engine->render('hello', ['name' => 'Velvet']);

        $this->assertSame('Hello, Velvet', $output);
    }

    public function test_includes_and_extends(): void
    {
        $viewDir = $this->tmpDir . '/views';
        $this->mkdir($viewDir . '/layouts');

        file_put_contents($viewDir . '/layouts/base.velvet.php', "<title>@yield('title')</title>{{ \$content }}");
        file_put_contents($viewDir . '/page.velvet.php', "@extends('layouts.base') @section('title')Page@endsection Body");

        $engine = new ViewEngine($viewDir, $this->tmpDir . '/cache/views');
        $output = $engine->render('page');

        $this->assertStringContainsString('<title>Page</title>', $output);
        $this->assertStringContainsString('Body', $output);
    }
}
