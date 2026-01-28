<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Core;

use VelvetCMS\Core\Application;
use VelvetCMS\Core\BaseModule;
use VelvetCMS\Core\ModuleManifest;
use VelvetCMS\Tests\Support\TestCase;

final class BaseModuleTest extends TestCase
{
    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = $this->tmpDir . '/modules/test-module';
        $this->mkdir($this->modulePath);
    }

    private function createModule(array $manifestData = []): TestModule
    {
        $data = array_merge([
            'name' => 'test-module',
            'version' => '1.0.0',
            'path' => $this->modulePath,
            'entry' => TestModule::class,
            'description' => 'Test module description',
        ], $manifestData);

        $manifest = ModuleManifest::fromArray($data['name'], $data);
        return new TestModule($this->modulePath, $manifest);
    }

    // === Basic Properties ===

    public function test_name_returns_manifest_name(): void
    {
        $module = $this->createModule(['name' => 'my-module']);
        $this->assertSame('my-module', $module->name());
    }

    public function test_version_returns_manifest_version(): void
    {
        $module = $this->createModule(['version' => '2.3.4']);
        $this->assertSame('2.3.4', $module->version());
    }

    public function test_description_returns_manifest_description(): void
    {
        $module = $this->createModule(['description' => 'A great module']);
        $this->assertSame('A great module', $module->description());
    }

    public function test_description_returns_empty_when_not_set(): void
    {
        $manifest = new ModuleManifest(
            name: 'test',
            version: '1.0.0',
            path: $this->modulePath,
            entry: TestModule::class,
            enabled: true,
            description: null,
        );
        $module = new TestModule($this->modulePath, $manifest);

        $this->assertSame('', $module->description());
    }

    // === Path Methods ===

    public function test_path_returns_base_path(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath, $module->path());
    }

    public function test_path_joins_subpath(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath . '/src/Controllers', $module->path('src/Controllers'));
    }

    public function test_path_normalizes_leading_slash(): void
    {
        $module = $this->createModule();
        $this->assertSame($this->modulePath . '/config', $module->path('/config'));
    }

    public function test_path_handles_trailing_slash_in_base(): void
    {
        $manifest = ModuleManifest::fromArray('test', [
            'path' => $this->modulePath . '/',  // With trailing slash
            'entry' => TestModule::class,
        ]);
        $module = new TestModule($this->modulePath . '/', $manifest);

        // Should not double slashes
        $this->assertStringNotContainsString('//', $module->path('src'));
    }

    // === Public Path ===

    public function test_public_path_returns_null_when_no_public_dir(): void
    {
        $module = $this->createModule();
        $this->assertNull($module->publicPath());
    }

    public function test_public_path_returns_path_when_dir_exists(): void
    {
        $publicDir = $this->modulePath . '/public';
        $this->mkdir($publicDir);

        $module = $this->createModule();
        $this->assertSame($publicDir, $module->publicPath());
    }

    // === Assets Prefix ===

    public function test_assets_prefix_returns_module_name(): void
    {
        $module = $this->createModule(['name' => 'blog-module']);
        $this->assertSame('blog-module', $module->assetsPrefix());
    }

    // === Migration Paths ===

    public function test_get_migration_paths_returns_empty_when_no_migrations_dir(): void
    {
        $module = $this->createModule();
        $this->assertSame([], $module->getMigrationPaths());
    }

    public function test_get_migration_paths_includes_default_dir(): void
    {
        $migrationsDir = $this->modulePath . '/database/migrations';
        $this->mkdir($migrationsDir);

        $module = $this->createModule();
        $this->assertContains($migrationsDir, $module->getMigrationPaths());
    }

    // === Manifest Access ===

    public function test_manifest_object_returns_manifest(): void
    {
        $module = $this->createModule();
        $manifest = $module->manifestObject();

        $this->assertInstanceOf(ModuleManifest::class, $manifest);
        $this->assertSame('test-module', $manifest->name);
    }

    public function test_manifest_config_returns_known_field(): void
    {
        $module = $this->createModule(['version' => '3.0.0']);
        $this->assertSame('3.0.0', $module->manifestConfig('version'));
    }

    public function test_manifest_config_returns_extra_field(): void
    {
        $module = $this->createModule([
            'custom_setting' => 'custom_value',
        ]);

        $this->assertSame('custom_value', $module->manifestConfig('custom_setting'));
    }

    public function test_manifest_config_returns_default_when_missing(): void
    {
        $module = $this->createModule();
        $this->assertSame('fallback', $module->manifestConfig('nonexistent', 'fallback'));
    }

    public function test_manifest_config_returns_null_default(): void
    {
        $module = $this->createModule();
        $this->assertNull($module->manifestConfig('missing'));
    }

    // === Register and Boot ===

    public function test_register_is_called(): void
    {
        $module = $this->createModule();
        $app = new Application($this->tmpDir);

        $module->register($app);

        $this->assertTrue($module->registerCalled);
    }

    public function test_boot_is_called(): void
    {
        $module = $this->createModule();
        $app = new Application($this->tmpDir);

        $module->boot($app);

        $this->assertTrue($module->bootCalled);
    }

    // === Config Merging ===

    public function test_merge_config_from_merges_module_config(): void
    {
        // Create a module config file
        $configPath = $this->modulePath . '/config/module.php';
        $this->mkdir(dirname($configPath));
        file_put_contents($configPath, '<?php return ["key" => "module_value", "only_module" => true];');

        // Set existing app config
        config(['testmodule' => ['key' => 'app_value', 'only_app' => true]]);

        $module = $this->createModule();
        $module->testMergeConfigFrom($configPath, 'testmodule');

        // App values should override module values
        $this->assertSame('app_value', config('testmodule.key'));
        // Module-only values should be added
        $this->assertTrue(config('testmodule.only_module'));
        // App-only values should remain
        $this->assertTrue(config('testmodule.only_app'));
    }

    public function test_merge_config_from_handles_missing_file(): void
    {
        $module = $this->createModule();

        // Should not throw
        $module->testMergeConfigFrom('/nonexistent/config.php', 'test');

        $this->assertTrue(true);
    }

    public function test_merge_config_from_handles_non_array_config(): void
    {
        $configPath = $this->modulePath . '/config/invalid.php';
        $this->mkdir(dirname($configPath));
        file_put_contents($configPath, '<?php return "not an array";');

        $module = $this->createModule();

        // Should not throw
        $module->testMergeConfigFrom($configPath, 'test');

        $this->assertTrue(true);
    }

    // === Views Loading ===

    public function test_load_views_from_registers_namespace(): void
    {
        $viewsPath = $this->modulePath . '/resources/views';
        $this->mkdir($viewsPath);
        file_put_contents($viewsPath . '/index.velvet.php', 'Module View');

        // Create view engine
        $app = new Application($this->tmpDir);
        $viewEngine = new \VelvetCMS\Services\ViewEngine(
            $this->tmpDir . '/views',
            $this->tmpDir . '/cache/views'
        );
        $app->instance('view', $viewEngine);
        Application::setInstance($app);

        $module = $this->createModule();
        $module->testLoadViewsFrom($viewsPath, 'testmod');

        $output = $viewEngine->render('testmod::index');
        $this->assertSame('Module View', $output);

        Application::clearInstance();
    }

    public function test_load_views_from_handles_missing_dir(): void
    {
        $app = new Application($this->tmpDir);
        Application::setInstance($app);

        $module = $this->createModule();

        // Should not throw
        $module->testLoadViewsFrom('/nonexistent/views', 'test');

        $this->assertTrue(true);
        Application::clearInstance();
    }
}

/**
 * Concrete test implementation of BaseModule.
 */
class TestModule extends BaseModule
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

    // Expose protected methods for testing
    public function testMergeConfigFrom(string $path, string $key): void
    {
        $this->mergeConfigFrom($path, $key);
    }

    public function testLoadViewsFrom(string $path, string $namespace): void
    {
        $this->loadViewsFrom($path, $namespace);
    }

    public function testLoadMigrationsFrom(string $path): void
    {
        $this->loadMigrationsFrom($path);
    }
}
