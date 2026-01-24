<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function test_path_trims_trailing_slash_and_strips_query(): void
    {
        $request = $this->makeRequest('GET', '/blog/post/?page=2');
        $this->assertSame('/blog/post', $request->path());
    }

    public function test_ajax_and_expects_json_detection(): void
    {
        $request = $this->makeRequest('GET', '/api', [], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
        $this->assertTrue($request->ajax());
        $this->assertTrue($request->expectsJson());
    }
}
