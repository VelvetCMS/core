<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

class Application
{
    private static ?Application $instance = null;

    public EventDispatcher $events;

    private array $services = [];
    private array $instances = [];
    private array $aliases = [];
    private array $reflectionCache = [];
    private array $providers = [];
    private bool $booted = false;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->events = new EventDispatcher();

        $this->instance(self::class, $this);
        $this->instance(Application::class, $this);

        $this->register(CoreServiceProvider::class);
    }

    public static function setInstance(Application $app): void
    {
        self::$instance = $app;
    }

    public static function getInstance(): Application
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application has not been set. Call Application::setInstance() during bootstrap.');
        }

        return self::$instance;
    }

    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    public static function clearInstance(): void
    {
        self::$instance = null;
    }

    public function register(string|ServiceProvider $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        $this->providers[] = $provider;

        if ($this->booted && method_exists($provider, 'boot')) {
            $provider->boot();
        }

        return $provider;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        date_default_timezone_set(config('app.timezone', 'UTC'));

        $this->events->dispatch('app.booting', $this);

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }

        $this->events->dispatch('app.booted', $this);
        $this->booted = true;
    }

    public function bind(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }

    public function singleton(string $name, callable $factory): void
    {
        $this->services[$name] = function () use ($name, $factory) {
            if (!array_key_exists($name, $this->instances)) {
                $this->instances[$name] = $factory();
            }

            return $this->instances[$name];
        };
    }

    public function get(string $name): mixed
    {
        $key = $this->resolveAlias($name);

        if (!isset($this->services[$key])) {
            throw new \RuntimeException("Service not found: {$name}");
        }

        return $this->services[$key]();
    }

    public function has(string $name): bool
    {
        $key = $this->resolveAlias($name);

        return isset($this->services[$key]);
    }

    private function resolveAlias(string $name): string
    {
        return $this->aliases[$name] ?? $name;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function alias(string $key, string $alias): void
    {
        $this->aliases[$alias] = $key;
    }

    public function instance(string $name, mixed $instance): void
    {
        $this->instances[$name] = $instance;
        $this->services[$name] = fn () => $this->instances[$name];
    }

    public function make(string $name): mixed
    {
        // First try to get from container; if not found, try to autowire
        if ($this->has($name)) {
            return $this->get($name);
        }

        if (class_exists($name)) {
            return $this->autowire($name);
        }

        throw new \RuntimeException("Service not found: {$name}");
    }

    private function autowire(string $className): object
    {
        if (!isset($this->reflectionCache[$className])) {
            $this->reflectionCache[$className] = $this->buildReflectionCache($className);
        }

        $cached = $this->reflectionCache[$className];

        if ($cached['constructor'] === null) {
            return new $className();
        }

        $dependencies = [];
        foreach ($cached['dependencies'] as $dep) {
            if ($dep['type'] === 'class') {
                $dependencies[] = $this->make($dep['value']);
            } else {
                $dependencies[] = $dep['value'];
            }
        }

        return new $className(...$dependencies);
    }

    private function buildReflectionCache(string $className): array
    {
        $reflection = new \ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Class {$className} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [
                'constructor' => null,
                'dependencies' => [],
            ];
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = ['type' => 'class', 'value' => $type->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = ['type' => 'default', 'value' => $parameter->getDefaultValue()];
            } elseif ($parameter->allowsNull()) {
                $dependencies[] = ['type' => 'default', 'value' => null];
            } else {
                throw new \RuntimeException(
                    "Cannot autowire {$className}: parameter \${$parameter->getName()} has no type hint or default value"
                );
            }
        }

        return [
            'constructor' => true,
            'dependencies' => $dependencies,
        ];
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function environment(): string
    {
        return (string) config('app.env', 'production');
    }

    public function isDebug(): bool
    {
        return (bool) config('app.debug', false);
    }

    public function registerDefaultRoutes(\VelvetCMS\Http\Routing\Router $router): void
    {
        $pages = $this->make('pages');
        $view = $this->make('view');

        $router->get('/', function (\VelvetCMS\Http\Request $request) use ($pages, $view) {
            try {
                $page = $pages->load('welcome');
                $layout = 'layouts/' . ($page->layout ?? 'default');
                return \VelvetCMS\Http\Response::html($view->render($layout, [
                    'page' => $page,
                    'content' => $page->html(),
                ]));
            } catch (\Exception $e) {
                return \VelvetCMS\Http\Response::html('<h1>Welcome to VelvetCMS</h1><p>Create a welcome.md page to get started.</p>');
            }
        });

        $router->get('/{slug*}', function (\VelvetCMS\Http\Request $request, string $slug) use ($pages, $view) {
            try {
                $page = $pages->load($slug);

                if (!$page->isPublished() && !config('app.debug', false)) {
                    return \VelvetCMS\Http\Response::notFound('Page not found');
                }

                $layout = 'layouts/' . ($page->layout ?? 'default');
                return \VelvetCMS\Http\Response::html($view->render($layout, [
                    'page' => $page,
                    'content' => $page->html(),
                ]));
            } catch (\VelvetCMS\Exceptions\NotFoundException $e) {
                return \VelvetCMS\Http\Response::notFound('Page not found');
            }
        });
    }
}
