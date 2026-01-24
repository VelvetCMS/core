<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Http\AssetServer;
use VelvetCMS\Http\Response;
use VelvetCMS\Tests\Support\TestCase;

final class AssetServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userPath = $this->tmpDir . '/views';
        $this->mkdir($userPath . '/assets/css');
        $this->mkdir($userPath . '/assets/js');
        $this->mkdir($userPath . '/assets/images');

        file_put_contents($userPath . '/assets/css/app.css', 'body { color: red; }');
        file_put_contents($userPath . '/assets/js/app.js', 'console.log("ok");');

        AssetServer::init($userPath);
    }

    public function test_serves_user_assets_with_cache_headers(): void
    {
        $request = $this->makeRequest('GET', '/assets/css/app.css');

        $response = AssetServer::serve($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('text/css', $response->getHeader('Content-Type') ?? '');
        $this->assertSame('public, max-age=31536000, immutable', $response->getHeader('Cache-Control'));
        $this->assertNotEmpty($response->getHeader('ETag'));
    }

    public function test_returns_null_for_unknown_extension(): void
    {
        $request = $this->makeRequest('GET', '/assets/css/app.exe');

        $response = AssetServer::serve($request);

        $this->assertNull($response);
    }

    public function test_serves_module_assets(): void
    {
        $modulePublic = $this->tmpDir . '/modules/admin/public';
        $this->mkdir($modulePublic);
        file_put_contents($modulePublic . '/app.js', 'console.log("admin");');

        AssetServer::module('admin', $modulePublic);

        $request = $this->makeRequest('GET', '/assets/admin/app.js');

        $response = AssetServer::serve($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('application/javascript', $response->getHeader('Content-Type') ?? '');
    }

    public function test_returns_not_modified_when_etag_matches(): void
    {
        $request = $this->makeRequest('GET', '/assets/js/app.js');
        $response = AssetServer::serve($request);

        $etag = $response?->getHeader('ETag');
        $this->assertNotEmpty($etag);

        $request = $this->makeRequest('GET', '/assets/js/app.js', [], ['If-None-Match' => $etag]);
        $response = AssetServer::serve($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(304, $response->getStatus());
        $this->assertSame($etag, $response->getHeader('ETag'));
    }

    public function test_does_not_escape_asset_root(): void
    {
        $userPath = $this->tmpDir . '/views';
        file_put_contents($userPath . '/assets/outside.txt', 'nope');

        $request = $this->makeRequest('GET', '/assets/css/../outside.txt');

        $response = AssetServer::serve($request);

        $this->assertNull($response);
    }
}
