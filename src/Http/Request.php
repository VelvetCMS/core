<?php

declare(strict_types=1);

namespace VelvetCMS\Http;

use VelvetCMS\Exceptions\ValidationException;

class Request
{
    private array $query;
    private array $request;
    private array $server;
    private array $files;
    private array $cookies;
    private ?string $pathPrefix = null;
    
    public function __construct()
    {
        $this->query = $_GET;
        $this->request = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;

        if ($this->isJson()) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $this->request = array_merge($this->request, $data);
            }
        }
    }

    public static function capture(): self
    {
        return new self();
    }
    
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
    
    public function path(): string
    {
        $path = $this->server['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        if ($path === '') {
            $path = '/';
        }

        if ($this->pathPrefix !== null && $this->pathPrefix !== '/') {
            $prefix = rtrim($this->pathPrefix, '/');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                if ($path === '' || $path === false) {
                    $path = '/';
                }
            }
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }
        
        return $path;
    }

    public function rawPath(): string
    {
        $path = $this->server['REQUEST_URI'] ?? '/';

        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        if ($path === '') {
            $path = '/';
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        return $path;
    }
    
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->rawPath();
    }

    public function host(): string
    {
        $host = $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
        $host = strtolower((string) $host);

        if (str_contains($host, ':')) {
            [$host] = explode(':', $host, 2);
        }

        return $host;
    }

    public function setPathPrefix(?string $prefix): void
    {
        if ($prefix === null || $prefix === '') {
            $this->pathPrefix = null;
            return;
        }

        $prefix = '/' . ltrim($prefix, '/');
        $this->pathPrefix = rtrim($prefix, '/');
    }
    
    public function isSecure(): bool
    {
        return isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }
    
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }
    
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }
    
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }
    
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }
    
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->request) || array_key_exists($key, $this->query);
    }
    
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
    
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }
    
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }
    
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }
    
    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }
    
    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }
    
    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }
    
    public function ajax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }
    
    public function expectsJson(): bool
    {
        return str_contains($this->header('Accept', ''), 'application/json');
    }
    
    public function validate(array $rules): array
    {
        return \VelvetCMS\Validation\Validator::make($this->all(), $rules)->validate();
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? '';
        return stripos($contentType, 'application/json') !== false;
    }
}