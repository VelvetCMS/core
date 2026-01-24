<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for rewritten suite.
 * Provides a per-test temp directory and default config overrides
 * so tests do not touch real user content or cache.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetGlobals();

        $this->tmpDir = sys_get_temp_dir() . '/velvet-tests-' . bin2hex(random_bytes(6));
        $this->mkdir($this->tmpDir);

        $this->seedConfig();
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
        $this->resetGlobals();

        // Cleanup FileDriver index
        if (function_exists('storage_path')) {
            $indexPath = storage_path('cache/file-driver-index.json');
            if (file_exists($indexPath)) {
                @unlink($indexPath);
            }
        }

        parent::tearDown();
    }

    /** Reset superglobals that Request::capture reads to avoid cross-test leakage. */
    private function resetGlobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    /** Override defaults so file/cache drivers stay in tmp space. */
    protected function seedConfig(): void
    {
        $cachePath = $this->tmpDir . '/cache';
        $contentPath = $this->tmpDir . '/content/pages';
        $viewPath = $this->tmpDir . '/views';

        $this->mkdir($cachePath);
        $this->mkdir($contentPath);
        $this->mkdir($viewPath);

        config([
            'app.debug' => true,
            'cache.default' => 'file',
            'cache.drivers.file.path' => $cachePath,
            'cache.prefix' => 'velvet-test',
            'content.driver' => 'file',
            'content.drivers.file.path' => $contentPath,
            'view.path' => $viewPath,
            'view.compiled' => 'cache/views',
        ]);
    }

    /**
     * Build a request by priming superglobals. Keeps tests isolated from real globals.
     */
    protected function makeRequest(string $method, string $uri, array $data = [], array $headers = []): \VelvetCMS\Http\Request
    {
        $_GET = $method === 'GET' ? $data : [];
        $_POST = $method !== 'GET' ? $data : [];
        $_FILES = [];
        $_COOKIE = [];
        $serverHeaders = [];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $_SERVER = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
        ], $serverHeaders);

        return \VelvetCMS\Http\Request::capture();
    }

    protected function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
