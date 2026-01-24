<?php

declare(strict_types=1);

namespace VelvetCMS\Core;

use VelvetCMS\Services\FileLogger;
use VelvetCMS\Exceptions\Handler;
use Psr\Log\LoggerInterface;
use VelvetCMS\Http\Routing\Router;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config
        $configRepository = ConfigRepository::getInstance();
        $this->app->instance('config', $configRepository);
        $this->app->instance(ConfigRepository::class, $configRepository);

        // Events
        $this->app->singleton('events', fn() => new EventDispatcher());
        $this->app->alias('events', EventDispatcher::class);

        // Logger
        $this->app->singleton('logger', function() {
            $logPath = storage_path('logs/velvet.log');
            $level = config('app.log_level', 'info');
            return new \VelvetCMS\Services\FileLogger($logPath, $level);
        });
        $this->app->alias('logger', \Psr\Log\LoggerInterface::class);

        // Exception Handler
        $this->app->singleton('exceptions.handler', function() {
            $renderers = (array) config('exceptions.renderers', []);
            $reporters = (array) config('exceptions.reporters', []);
            $logger = $this->app->make(\Psr\Log\LoggerInterface::class);

            return new \VelvetCMS\Exceptions\Handler(
                $this->app->get('events'),
                $logger,
                $renderers,
                $reporters
            );
        });
        $this->app->alias('exceptions.handler', \VelvetCMS\Exceptions\ExceptionHandlerInterface::class);
        $this->app->alias('exceptions.handler', \VelvetCMS\Exceptions\Handler::class);

        // Router
        $this->app->singleton('router', function() {
            $router = new Router($this->app->get('events'));
            $router->setApp($this->app);

            $middlewareConfig = (array) config('http.middleware', []);
            $aliases = (array) ($middlewareConfig['aliases'] ?? []);
            $global = (array) ($middlewareConfig['global'] ?? []);

            $aliases = array_merge(
                ['errors' => \VelvetCMS\Http\Middleware\ErrorHandlingMiddleware::class],
                $aliases
            );

            foreach ($aliases as $name => $definition) {
                $router->registerMiddleware($name, $definition);
            }

            if ($global === []) {
                $global = ['errors'];
            } else {
                $global = array_values(array_unique(array_merge(['errors'], $global)));
            }

            foreach ($global as $middleware) {
                $router->pushMiddleware($middleware);
            }

            return $router;
        });
        $this->app->alias('router', Router::class);

        // Database
        $this->app->singleton('db', function() {
            $config = config('db');

            if (!is_array($config)) {
                throw new \RuntimeException('Database configuration not found.');
            }

            return new \VelvetCMS\Database\Connection($config);
        });
        $this->app->alias('db', \VelvetCMS\Database\Connection::class);

        // Cache
        $this->app->singleton('cache', function() {
            $driver = config('cache.default', 'file');
            $config = config("cache.drivers.{$driver}", []);
            $config['path'] = $config['path'] ?? storage_path('cache');
            $prefix = $config['prefix'] ?? config('cache.prefix', 'velvet');
            if (tenant_enabled() && tenant_id() !== null) {
                $prefix = rtrim($prefix, ':') . ':' . tenant_id();
            }
            $config['prefix'] = $prefix;

            return match ($driver) {
                'file' => new \VelvetCMS\Drivers\Cache\FileCache($config),
                'redis' => new \VelvetCMS\Drivers\Cache\RedisCache($config),
                'apcu' => new \VelvetCMS\Drivers\Cache\ApcuCache($config),
                default => new \VelvetCMS\Drivers\Cache\FileCache($config),
            };
        });
        $this->app->alias('cache', \VelvetCMS\Contracts\CacheDriver::class);

        // Tenant context (nullable)
        $this->app->singleton('tenant', fn() => \VelvetCMS\Core\Tenancy\TenancyManager::current());
        $this->app->alias('tenant', \VelvetCMS\Core\Tenancy\TenantContext::class);

        $this->app->singleton('cache.tags', function() {
            return new \VelvetCMS\Support\Cache\CacheTagManager(
                $this->app->make(\VelvetCMS\Contracts\CacheDriver::class)
            );
        });
        $this->app->alias('cache.tags', \VelvetCMS\Support\Cache\CacheTagManager::class);

        // Markdown Parser
        $this->app->singleton(\VelvetCMS\Contracts\ParserInterface::class, function() {
            $driver = config('content.parser.driver', 'commonmark');
            $driverConfig = config("content.parser.drivers.{$driver}", []);

            return (new \VelvetCMS\Services\Parsers\ParserFactory())->make($driver, $driverConfig);
        });

        // Content parser (markdown + velvet blocks)
        $this->app->singleton('parser', function() {
            return new \VelvetCMS\Services\ContentParser(
                $this->app->make(\VelvetCMS\Contracts\CacheDriver::class),
                $this->app->make(\VelvetCMS\Contracts\ParserInterface::class)
            );
        });
        $this->app->alias('parser', \VelvetCMS\Services\ContentParser::class);

        // View engine
        $this->app->singleton('view', fn() => new \VelvetCMS\Services\ViewEngine());
        $this->app->alias('view', \VelvetCMS\Services\ViewEngine::class);

        // Migrations
        $this->app->singleton(\VelvetCMS\Database\Migrations\MigrationRepository::class, function() {
            return new \VelvetCMS\Database\Migrations\MigrationRepository(
                $this->app->make(\VelvetCMS\Database\Connection::class)
            );
        });

        $this->app->singleton('migrator', function() {
            return new \VelvetCMS\Database\Migrations\Migrator(
                $this->app->make(\VelvetCMS\Database\Connection::class),
                $this->app->make(\VelvetCMS\Database\Migrations\MigrationRepository::class)
            );
        });
        $this->app->alias('migrator', \VelvetCMS\Database\Migrations\Migrator::class);

        $this->app->singleton('session', function() {
            return new \VelvetCMS\Services\SessionManager();
        });
        $this->app->alias('session', \VelvetCMS\Services\SessionManager::class);

        // Scheduler
        $this->app->singleton('schedule', function() {
            return new \VelvetCMS\Scheduling\Schedule();
        });
        $this->app->alias('schedule', \VelvetCMS\Scheduling\Schedule::class);

        // Storage
        $this->app->singleton('storage', function() {
            return new \VelvetCMS\Services\StorageManager(config('filesystems', []));
        });
        $this->app->alias('storage', \VelvetCMS\Services\StorageManager::class);

        // Module Manager
        $this->app->singleton('modules', function() {
            $manager = new ModuleManager($this->app);
            $manager->load();
            $manager->register();
            return $manager;
        });
        $this->app->alias('modules', ModuleManager::class);

        $this->app->events->dispatch('migrations.registering', $this->app);

        $this->app->events->listen('commands.registering', function ($registry) {
            $registry->register('schedule:run', \VelvetCMS\Commands\ScheduleRunCommand::class);
        });
    }

    public function boot(): void
    {
        if ($this->app->has('modules')) {
            $this->app->make('modules')->boot();
        }

        // Register WebCron route if configured
        if (config('app.cron_enabled', false)) {
            $router = $this->app->make('router');
            $router->get('/system/cron', function() {
                $controller = new \VelvetCMS\Http\Controllers\WebCronController(
                    $this->app,
                    $this->app->make(\VelvetCMS\Scheduling\Schedule::class)
                );
                $controller->run();
            });
        }
    }
}
