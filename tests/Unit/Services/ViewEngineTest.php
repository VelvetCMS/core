<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Services;

use RuntimeException;
use VelvetCMS\Services\ViewEngine;
use VelvetCMS\Tests\Support\TestCase;

final class ViewEngineTest extends TestCase
{
    private string $viewDir;
    private string $cacheDir;
    private ViewEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewDir = $this->tmpDir . '/views';
        $this->cacheDir = $this->tmpDir . '/cache/views';
        $this->mkdir($this->viewDir);
        $this->mkdir($this->cacheDir);

        $this->engine = new ViewEngine($this->viewDir, $this->cacheDir);
    }

    // === Basic Rendering ===

    public function test_renders_simple_view(): void
    {
        file_put_contents($this->viewDir . '/hello.velvet.php', 'Hello, {{ $name }}');

        $output = $this->engine->render('hello', ['name' => 'Velvet']);

        $this->assertSame('Hello, Velvet', $output);
    }

    public function test_renders_view_with_dot_notation(): void
    {
        $this->mkdir($this->viewDir . '/pages');
        file_put_contents($this->viewDir . '/pages/home.velvet.php', 'Home Page');

        $output = $this->engine->render('pages.home');

        $this->assertSame('Home Page', $output);
    }

    public function test_throws_for_missing_view(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View 'nonexistent' not found");

        $this->engine->render('nonexistent');
    }

    // === Escaping ===

    public function test_double_braces_escape_html(): void
    {
        file_put_contents($this->viewDir . '/escape.velvet.php', '{{ $html }}');

        $output = $this->engine->render('escape', ['html' => '<script>alert("xss")</script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_raw_braces_do_not_escape(): void
    {
        file_put_contents($this->viewDir . '/raw.velvet.php', '{!! $html !!}');

        $output = $this->engine->render('raw', ['html' => '<strong>Bold</strong>']);

        $this->assertSame('<strong>Bold</strong>', $output);
    }

    public function test_escaped_braces_are_not_processed(): void
    {
        file_put_contents($this->viewDir . '/literal.velvet.php', '@{{ $notParsed }}');

        $output = $this->engine->render('literal', ['notParsed' => 'value']);

        $this->assertSame('{{ $notParsed }}', $output);
    }

    // === Layouts and Sections ===

    public function test_includes_and_extends(): void
    {
        $this->mkdir($this->viewDir . '/layouts');

        file_put_contents($this->viewDir . '/layouts/base.velvet.php', "<title>@yield('title')</title>{{ \$content }}");
        file_put_contents($this->viewDir . '/page.velvet.php', "@extends('layouts.base') @section('title')Page@endsection Body");

        $output = $this->engine->render('page');

        $this->assertStringContainsString('<title>Page</title>', $output);
        $this->assertStringContainsString('Body', $output);
    }

    public function test_yield_with_default_value(): void
    {
        $this->mkdir($this->viewDir . '/layouts');
        file_put_contents($this->viewDir . '/layouts/app.velvet.php', "@yield('sidebar', 'Default Sidebar')");
        file_put_contents($this->viewDir . '/no-sidebar.velvet.php', "@extends('layouts.app')");

        $output = $this->engine->render('no-sidebar');

        $this->assertSame('Default Sidebar', $output);
    }

    public function test_section_overrides_yield_default(): void
    {
        $this->mkdir($this->viewDir . '/layouts');
        file_put_contents($this->viewDir . '/layouts/app.velvet.php', "@yield('sidebar', 'Default')");
        file_put_contents($this->viewDir . '/with-sidebar.velvet.php', "@extends('layouts.app') @section('sidebar')Custom@endsection");

        $output = $this->engine->render('with-sidebar');

        $this->assertSame('Custom', $output);
    }

    public function test_throws_for_missing_layout(): void
    {
        file_put_contents($this->viewDir . '/orphan.velvet.php', "@extends('layouts.missing')");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Layout 'layouts/missing' not found");

        $this->engine->render('orphan');
    }

    // === Control Structures ===

    public function test_if_directive(): void
    {
        file_put_contents($this->viewDir . '/if.velvet.php', '@if($show)Visible@endif');

        $this->assertSame('Visible', $this->engine->render('if', ['show' => true]));
        $this->assertSame('', $this->engine->render('if', ['show' => false]));
    }

    public function test_if_else_directive(): void
    {
        file_put_contents($this->viewDir . '/ifelse.velvet.php', '@if($condition)Yes@else No@endif');

        $this->assertSame('Yes', $this->engine->render('ifelse', ['condition' => true]));
        $this->assertSame(' No', $this->engine->render('ifelse', ['condition' => false]));
    }

    public function test_elseif_directive(): void
    {
        file_put_contents(
            $this->viewDir . '/elseif.velvet.php',
            '@if($val === 1)One@elseif($val === 2)Two@else Other@endif'
        );

        $this->assertSame('One', $this->engine->render('elseif', ['val' => 1]));
        $this->assertSame('Two', $this->engine->render('elseif', ['val' => 2]));
        $this->assertSame(' Other', $this->engine->render('elseif', ['val' => 3]));
    }

    public function test_foreach_directive(): void
    {
        file_put_contents(
            $this->viewDir . '/foreach.velvet.php',
            '@foreach($items as $item){{ $item }}@endforeach'
        );

        $output = $this->engine->render('foreach', ['items' => ['A', 'B', 'C']]);

        $this->assertSame('ABC', $output);
    }

    public function test_for_directive(): void
    {
        file_put_contents(
            $this->viewDir . '/for.velvet.php',
            '@for($i = 0; $i < 3; $i++){{ $i }}@endfor'
        );

        $output = $this->engine->render('for');

        $this->assertSame('012', $output);
    }

    public function test_while_directive(): void
    {
        file_put_contents(
            $this->viewDir . '/while.velvet.php',
            '@php $i = 0; @endphp@while($i < 3){{ $i }}@php $i++; @endphp@endwhile'
        );

        $output = $this->engine->render('while');

        $this->assertSame('012', $output);
    }

    // === Include Directive ===

    public function test_include_partial(): void
    {
        $this->mkdir($this->viewDir . '/partials');
        file_put_contents($this->viewDir . '/partials/button.velvet.php', '<button>{{ $label }}</button>');
        file_put_contents($this->viewDir . '/form.velvet.php', "@include('partials.button', ['label' => 'Submit'])");

        $output = $this->engine->render('form');

        $this->assertSame('<button>Submit</button>', $output);
    }

    public function test_include_inherits_parent_data(): void
    {
        $this->mkdir($this->viewDir . '/partials');
        file_put_contents($this->viewDir . '/partials/greeting.velvet.php', 'Hello, {{ $name }}!');
        file_put_contents($this->viewDir . '/wrapper.velvet.php', "@include('partials.greeting')");

        $output = $this->engine->render('wrapper', ['name' => 'World']);

        $this->assertSame('Hello, World!', $output);
    }

    // === Namespaces ===

    public function test_namespace_resolution(): void
    {
        $moduleViewDir = $this->tmpDir . '/modules/blog/views';
        $this->mkdir($moduleViewDir);
        file_put_contents($moduleViewDir . '/index.velvet.php', 'Blog Index');

        $this->engine->namespace('blog', $moduleViewDir);
        $output = $this->engine->render('blog::index');

        $this->assertSame('Blog Index', $output);
    }

    public function test_user_views_override_namespace(): void
    {
        // Module view
        $moduleViewDir = $this->tmpDir . '/modules/shop/views';
        $this->mkdir($moduleViewDir);
        file_put_contents($moduleViewDir . '/cart.velvet.php', 'Module Cart');

        // User override
        $this->mkdir($this->viewDir . '/shop');
        file_put_contents($this->viewDir . '/shop/cart.velvet.php', 'Custom Cart');

        $this->engine->namespace('shop', $moduleViewDir);
        $output = $this->engine->render('shop::cart');

        $this->assertSame('Custom Cart', $output);
    }

    public function test_throws_for_unknown_namespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View 'unknown::view' not found");

        $this->engine->render('unknown::view');
    }

    // === Shared Data ===

    public function test_shared_data_available_in_all_views(): void
    {
        file_put_contents($this->viewDir . '/shared.velvet.php', '{{ $siteName }}');

        $this->engine->share('siteName', 'VelvetCMS');
        $output = $this->engine->render('shared');

        $this->assertSame('VelvetCMS', $output);
    }

    public function test_local_data_overrides_shared(): void
    {
        file_put_contents($this->viewDir . '/override.velvet.php', '{{ $value }}');

        $this->engine->share('value', 'shared');
        $output = $this->engine->render('override', ['value' => 'local']);

        $this->assertSame('local', $output);
    }

    // === View Existence ===

    public function test_exists_returns_true_for_existing_view(): void
    {
        file_put_contents($this->viewDir . '/exists.velvet.php', 'content');

        $this->assertTrue($this->engine->exists('exists'));
    }

    public function test_exists_returns_false_for_missing_view(): void
    {
        $this->assertFalse($this->engine->exists('missing'));
    }

    // === Compile String ===

    public function test_compile_string_processes_template(): void
    {
        $output = $this->engine->compileString('Hello, {{ $name }}!', ['name' => 'Test']);

        $this->assertSame('Hello, Test!', $output);
    }

    public function test_compile_string_with_directives(): void
    {
        $output = $this->engine->compileString(
            '@foreach($items as $i){{ $i }}@endforeach',
            ['items' => [1, 2, 3]]
        );

        $this->assertSame('123', $output);
    }

    // === Safe Mode ===

    public function test_safe_strips_php_blocks(): void
    {
        $output = $this->engine->safe('@php echo "unsafe"; @endphp Safe content', []);

        $this->assertStringNotContainsString('unsafe', $output);
        $this->assertStringContainsString('Safe content', $output);
    }

    public function test_safe_converts_raw_to_escaped(): void
    {
        $output = $this->engine->safe('{!! $html !!}', ['html' => '<b>Bold</b>']);

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringContainsString('&lt;b&gt;', $output);
    }

    // === Cache ===

    public function test_clears_cache(): void
    {
        file_put_contents($this->viewDir . '/cached.velvet.php', 'content');
        $this->engine->render('cached');

        // Cache file should exist
        $cacheFiles = glob($this->cacheDir . '/*.php');
        $this->assertNotEmpty($cacheFiles);

        $this->engine->clearCache();

        // Cache should be empty
        $cacheFiles = glob($this->cacheDir . '/*.php');
        $this->assertEmpty($cacheFiles);
    }

    // === PHP Directive ===

    public function test_php_directive(): void
    {
        file_put_contents($this->viewDir . '/php.velvet.php', '@php $x = 5; @endphp{{ $x }}');

        $output = $this->engine->render('php');

        $this->assertSame('5', $output);
    }

    // === CSRF and Method Directives ===

    public function test_csrf_directive(): void
    {
        file_put_contents($this->viewDir . '/csrf.velvet.php', '@csrf');

        $output = $this->engine->render('csrf');

        $this->assertStringContainsString('_token', $output);
        $this->assertStringContainsString('hidden', $output);
    }

    public function test_method_directive(): void
    {
        file_put_contents($this->viewDir . '/method.velvet.php', "@method('PUT')");

        $output = $this->engine->render('method');

        $this->assertStringContainsString('_method', $output);
        $this->assertStringContainsString('PUT', $output);
    }
}
