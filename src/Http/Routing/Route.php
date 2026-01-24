<?php

declare(strict_types=1);

namespace VelvetCMS\Http\Routing;

final class Route
{
    private mixed $handler;

    /** @param array<int, string> $methods */
    /** @param array<int, string|callable> $middleware */
    public function __construct(
        private int $id,
        private array $methods,
        private string $path,
        private string $pattern,
        callable|array $handler,
        private array $middleware = [],
        private ?string $name = null,
    ) {
        $this->handler = $handler;
    }

    public function id(): int
    {
        return $this->id;
    }

    /** @return array<int, string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function handler(): callable|array
    {
        /** @var callable|array $handler */
        $handler = $this->handler;
        return $handler;
    }

    /** @return array<int, string|callable> */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /** @param array<int, string|callable> $middleware */
    public function addMiddleware(array $middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);
    }

    /**
     * @return array{matched: bool, params: array<string, string>}
     */
    public function match(string $path): array
    {
        if (!preg_match($this->pattern, $path, $matches)) {
            return ['matched' => false, 'params' => []];
        }

        /** @var array<string, string> $params */
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return ['matched' => true, 'params' => $params];
    }

    /**
     * Cached route definition (stable array shape for route:cache).
     *
     * @return array{
     *   id:int,
     *   methods:array<int,string>,
     *   path:string,
     *   pattern:string,
     *   handler:callable|array,
     *   middleware:array<int, string|callable>,
     *   name:?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'methods' => $this->methods,
            'path' => $this->path,
            'pattern' => $this->pattern,
            'handler' => $this->handler(),
            'middleware' => $this->middleware,
            'name' => $this->name,
        ];
    }

    /**
     * @param array{
     *   id:int,
     *   methods:array<int,string>,
     *   path:string,
     *   pattern:string,
     *   handler:callable|array,
     *   middleware:array<int, string|callable>,
     *   name:?string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            array_map('strtoupper', $data['methods']),
            $data['path'],
            $data['pattern'],
            $data['handler'],
            $data['middleware'] ?? [],
            $data['name'] ?? null,
        );
    }
}
