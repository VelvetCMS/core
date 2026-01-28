<?php

declare(strict_types=1);

namespace VelvetCMS\Http;

class Response
{
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    private ?string $filePath = null;

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array|object $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::html($message, 404);
    }

    public static function error(string $message, int $status = 500): self
    {
        return self::html($message, $status);
    }

    public static function methodNotAllowed(array $allowed): self
    {
        $allowedHeader = implode(', ', array_unique(array_map('strtoupper', $allowed)));

        return (new self('405 Method Not Allowed', 405))
            ->header('Allow', $allowedHeader);
    }

    public static function file(string $path, ?string $mimeType = null, array $headers = [], int $status = 200): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("File not readable: {$path}");
        }

        $instance = new self('', $status);
        $instance->filePath = $path; // Add this property to class

        $detectedMime = $mimeType;

        if ($detectedMime === null) {
            $detectedMime = function_exists('mime_content_type')
                ? mime_content_type($path) ?: 'application/octet-stream'
                : 'application/octet-stream';
        }

        $fileSize = filesize($path);

        $defaultHeaders = [
            'Content-Type' => $detectedMime,
            'Content-Length' => (string) ($fileSize !== false ? $fileSize : 0),
        ];

        return $instance->headers(array_merge($defaultHeaders, $headers));
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if (isset($this->filePath)) {
            $stream = fopen($this->filePath, 'rb');
            fpassthru($stream);
            fclose($stream);
        } else {
            echo $this->content;
        }
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }
}
