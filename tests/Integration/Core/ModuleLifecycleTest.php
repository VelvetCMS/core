<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Core;

use VelvetCMS\Tests\Support\ModuleManagerTestCase;

final class ModuleLifecycleTest extends ModuleManagerTestCase
{
    public function test_disabled_modules_are_skipped_during_boot(): void
    {
        $modulePath = $this->createManagerModule(
            'disabled-module',
            'DisabledModule',
            [
                'version' => '1.0.0',
                'entry' => 'DisabledModule\\Module',
            ],
            <<<'PHP'
<?php

declare(strict_types=1);

namespace DisabledModule;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\BaseModule;

final class Module extends BaseModule
{
    public function register(Application $app): void
    {
        $app->instance('disabled-loaded', true);
    }
}
PHP,
        );

        $this->writeCompiledModules([
            [
                'name' => 'disabled-module',
                'version' => '1.0.0',
                'entry' => 'DisabledModule\\Module',
                'path' => $modulePath,
                'enabled' => false,
            ],
        ]);
        $this->writeAutoloadMap([
            'DisabledModule\\' => $modulePath . '/src',
        ]);

        (new \VelvetCMS\Core\ModuleManager($this->app))->load()->register()->boot();

        $this->assertFalse($this->app->has('disabled-loaded'));
    }

    public function test_load_and_boot_modules_in_dependency_order(): void
    {
        $baseModulePath = $this->createManagerModule(
            'base-module',
            'BaseModule',
            [
                'version' => '1.0.0',
                'entry' => 'BaseModule\\Module',
            ],
            <<<'PHP'
<?php

declare(strict_types=1);

namespace BaseModule;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\BaseModule as CoreBaseModule;

final class Module extends CoreBaseModule
{
    public function register(Application $app): void
    {
        $app->instance('base-loaded', true);
    }

    public function boot(Application $app): void
    {
        $app->instance('base-booted', true);
    }
}
PHP,
        );

        $dependentModulePath = $this->createManagerModule(
            'dependent-module',
            'DependentModule',
            [
                'version' => '1.0.0',
                'entry' => 'DependentModule\\Module',
                'requires' => ['base-module' => '^1.0'],
            ],
            <<<'PHP'
<?php

declare(strict_types=1);

namespace DependentModule;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\BaseModule as CoreBaseModule;

final class Module extends CoreBaseModule
{
    public function register(Application $app): void
    {
        $app->instance('dependent-loaded', true);
    }

    public function boot(Application $app): void
    {
        $app->instance('dependent-booted', true);
    }
}
PHP,
        );

        $this->writeCompiledModules([
            [
                'name' => 'base-module',
                'version' => '1.0.0',
                'entry' => 'BaseModule\\Module',
                'path' => $baseModulePath,
                'enabled' => true,
            ],
            [
                'name' => 'dependent-module',
                'version' => '1.0.0',
                'entry' => 'DependentModule\\Module',
                'path' => $dependentModulePath,
                'enabled' => true,
                'requires' => ['base-module' => '^1.0'],
            ],
        ]);
        $this->writeAutoloadMap([
            'BaseModule\\' => $baseModulePath . '/src',
            'DependentModule\\' => $dependentModulePath . '/src',
        ]);

        (new \VelvetCMS\Core\ModuleManager($this->app))->load()->register()->boot();

        $this->assertTrue($this->app->has('base-loaded'));
        $this->assertTrue($this->app->has('base-booted'));
        $this->assertTrue($this->app->has('dependent-loaded'));
        $this->assertTrue($this->app->has('dependent-booted'));
    }
}
