<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Http;

use VelvetCMS\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_method_not_allowed_sets_allow_header(): void
    {
        $resp = Response::methodNotAllowed(['post', 'get', 'POST']);
        $this->assertSame(405, $resp->getStatus());
        $allow = $resp->getHeaders()['Allow'] ?? '';
        $parts = array_map('trim', explode(',', $allow));
        sort($parts);
        $this->assertSame(['GET', 'POST'], $parts);
    }

    public function test_json_encodes_payload(): void
    {
        $resp = Response::json(['ok' => true], 201);
        $this->assertSame(201, $resp->getStatus());
        $this->assertSame(['Content-Type' => 'application/json; charset=utf-8'], $resp->getHeaders());
        $this->assertSame('{"ok":true}', $resp->getContent());
    }

    public function test_file_response_sets_headers(): void
    {
        $path = sys_get_temp_dir() . '/velvet-response-test.txt';
        file_put_contents($path, 'hello');

        $resp = Response::file($path, 'text/plain');

        $this->assertSame('text/plain', $resp->getHeader('Content-Type'));
        $this->assertSame('5', $resp->getHeader('Content-Length'));
        $this->assertSame(200, $resp->getStatus());

        @unlink($path);
    }
}
