<?php

declare(strict_types=1);

namespace VelvetCMS\Http;

/** Serves static assets from user views and modules. Fast-path before routing. */
class AssetServer
{
    private static array $modulePaths = [];
    private static ?string $userPath = null;

    private const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'map' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
    ];

    private const ASSET_DIRS = ['css', 'js', 'img', 'images', 'fonts', 'media', 'files'];

    public static function init(?string $userPath = null): void
    {
        self::$userPath = $userPath ?? view_path();
    }

    public static function module(string $name, string $publicPath): void
    {
        self::$modulePaths[strtolower($name)] = rtrim($publicPath, '/');
    }

    /** Returns Response if found, null otherwise. */
    public static function serve(Request $request): ?Response
    {
        $path = $request->path();

        if (!str_starts_with($path, '/assets/')) {
            return null;
        }

        // Get path after /assets/
        $assetPath = substr($path, 8);
        if ($assetPath === '') {
            return null;
        }

        // Only serve known static file extensions
        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$extension])) {
            return null;
        }

        // Parse: first segment determines source
        $segments = explode('/', $assetPath, 2);
        $first = $segments[0];
        $rest = $segments[1] ?? '';

        if ($rest === '') {
            return null;
        }

        // Check if first segment is an asset directory (css, js, img, etc.)
        // If so, serve from user views: /assets/css/... → user/views/assets/css/...
        if (in_array($first, self::ASSET_DIRS, true)) {
            return self::serveUserAsset($assetPath, $extension, $request);
        }

        // Otherwise, first segment is module name: /assets/admin/... → module public dir
        return self::serveModuleAsset($first, $rest, $extension, $request);
    }

    private static function serveUserAsset(string $relativePath, string $extension, Request $request): ?Response
    {
        if (self::$userPath === null) {
            self::init();
        }

        $filePath = self::$userPath . '/assets/' . self::sanitize($relativePath);
        return self::tryServeFile($filePath, self::$userPath, $extension, $request);
    }

    private static function serveModuleAsset(string $module, string $relativePath, string $extension, Request $request): ?Response
    {
        $module = strtolower($module);

        if (!isset(self::$modulePaths[$module])) {
            return null;
        }

        $publicDir = self::$modulePaths[$module];
        $filePath = $publicDir . '/' . self::sanitize($relativePath);
        return self::tryServeFile($filePath, $publicDir, $extension, $request);
    }

    private static function tryServeFile(string $filePath, string $rootDir, string $extension, Request $request): ?Response
    {
        $realRoot = realpath($rootDir);
        $realFile = realpath($filePath);

        // Security: file must exist and be within root directory
        if (!$realFile || !$realRoot || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
            return null;
        }

        return self::respond($realFile, $extension, $request);
    }

    private static function respond(string $path, string $extension, Request $request): Response
    {
        $mtime = filemtime($path) ?: time();
        $size = filesize($path);
        $etag = sprintf('"%x-%x"', $mtime, $size);
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        // Check If-None-Match (ETag)
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null) {
            $etags = array_map('trim', explode(',', $ifNoneMatch));
            if (in_array($etag, $etags, true) || in_array('*', $etags, true)) {
                return self::notModified($etag, $lastModified);
            }
        }

        // Check If-Modified-Since
        $ifModifiedSince = $request->header('If-Modified-Since');
        if ($ifModifiedSince !== null) {
            $since = strtotime($ifModifiedSince);
            if ($since !== false && $since >= $mtime) {
                return self::notModified($etag, $lastModified);
            }
        }

        return Response::file($path, self::MIME_TYPES[$extension] ?? null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]);
    }

    private static function notModified(string $etag, string $lastModified): Response
    {
        return (new Response('', 304))->headers([
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]);
    }

    private static function sanitize(string $path): string
    {
        // Remove directory traversal attempts and null bytes
        return str_replace(['../', '..\\', "\0", '//'], ['', '', '', '/'], $path);
    }

    public static function getModulePaths(): array
    {
        return self::$modulePaths;
    }
}
