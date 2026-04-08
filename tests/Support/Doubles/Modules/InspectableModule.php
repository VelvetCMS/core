<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support\Doubles\Modules;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\BaseModule;

final class InspectableModule extends BaseModule
{
    public bool $registerCalled = false;
    public bool $bootCalled = false;

    public function register(Application $app): void
    {
        $this->registerCalled = true;
    }

    public function boot(Application $app): void
    {
        $this->bootCalled = true;
    }

    public function exposeLoadViewsFrom(string $path, string $namespace): void
    {
        $this->loadViewsFrom($path, $namespace);
    }

    public function exposeLoadMigrationsFrom(string $path): void
    {
        $this->loadMigrationsFrom($path);
    }
}
