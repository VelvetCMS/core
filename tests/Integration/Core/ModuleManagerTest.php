<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use VelvetCMS\Core\Application;
use VelvetCMS\Core\ModuleManager;
use VelvetCMS\Contracts\Module;
use VelvetCMS\Exceptions\ModuleException;

class ModuleManagerTest extends TestCase
{
    private string $testBasePath;
    private Application $app;
    private ModuleManager $moduleManager;
    
    protected function setUp(): void
    {
        $this->testBasePath = __DIR__ . '/../fixtures/module-test';
        
        // Clean and create test directory
        if (is_dir($this->testBasePath)) {
            $this->rrmdir($this->testBasePath);
        }
        mkdir($this->testBasePath, 0755, true);
        mkdir($this->testBasePath . '/storage', 0755, true);
        mkdir($this->testBasePath . '/config', 0755, true);
        mkdir($this->testBasePath . '/modules', 0755, true);
        
        // Create minimal Application with config
        if (!defined('VELVET_BASE_PATH')) {
            define('VELVET_BASE_PATH', $this->testBasePath);
        }
        
        $this->app = new Application($this->testBasePath);
        
        // Set config directly in the app
        config(['modules.paths' => [$this->testBasePath . '/modules/*']]);
        config(['modules.modules' => []]);
        config(['modules.auto_discover' => true]);
        
        $this->moduleManager = new ModuleManager($this->app);
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->testBasePath)) {
            $this->rrmdir($this->testBasePath);
        }
    }
    
    public function testDiscoverModulesFromFilesystem(): void
    {
        // Create test module
        $this->createTestModule('test-module', [
            'name' => 'test-module',
            'version' => '1.0.0',
            'entry' => 'TestModule\\TestModule',
        ]);
        
        $discovered = $this->moduleManager->discover();
        
        $this->assertArrayHasKey('test-module', $discovered);
        $this->assertSame('1.0.0', $discovered['test-module']['version']);
    }
    
    public function testValidateModuleWithMissingEntry(): void
    {
        $manifest = [
            'name' => 'broken-module',
            'version' => '1.0.0',
            // Missing 'entry'
        ];
        
        $issues = $this->moduleManager->validate('broken-module', $manifest);
        
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('entry', $issues[0]);
    }
    
    public function testValidateModuleWithIncompatibleCoreVersion(): void
    {
        $manifest = [
            'name' => 'future-module',
            'version' => '1.0.0',
            'entry' => 'FutureModule\\Module',
            'requires' => [
                'core' => '^99.0',
            ],
        ];
        
        $issues = $this->moduleManager->validate('future-module', $manifest);
        
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('core', $issues[0]);
    }

    public function testValidateModuleFailsWhenDependencyMissing(): void
    {
        $manifest = [
            'name' => 'needs-dependency',
            'version' => '1.0.0',
            'entry' => 'NeedsDependency\\Module',
            'requires' => ['other-module' => '^1.0'],
        ];

        $issues = $this->moduleManager->validate('needs-dependency', $manifest);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('other-module', implode(' ', $issues));
    }

    public function testValidateModuleChecksDependencyVersion(): void
    {
        $this->createTestModule('base-module', [
            'name' => 'base-module',
            'version' => '0.5.0',
            'entry' => 'BaseModule\\BaseModule',
        ]);

        $discovered = $this->moduleManager->discover();

        $manifest = [
            'name' => 'needs-base',
            'version' => '1.0.0',
            'entry' => 'NeedsBase\\Module',
            'requires' => ['base-module' => '^1.0'],
        ];

        $issues = $this->moduleManager->validate('needs-base', $manifest, $discovered);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('base-module', implode(' ', $issues));
    }
    
    public function testResolveLoadOrderWithDependencies(): void
    {
        $modules = [
            'module-a' => [
                'name' => 'module-a',
                'version' => '1.0.0',
                'requires' => ['module-b' => '^1.0'],
            ],
            'module-b' => [
                'name' => 'module-b',
                'version' => '1.0.0',
                'requires' => [],
            ],
            'module-c' => [
                'name' => 'module-c',
                'version' => '1.0.0',
                'requires' => ['module-a' => '^1.0'],
            ],
        ];
        
        $loadOrder = $this->moduleManager->resolveLoadOrder($modules);
        
        // module-b should load first, then module-a, then module-c
        $this->assertSame(['module-b', 'module-a', 'module-c'], $loadOrder);
    }

    public function testDisabledModulesAreSkippedDuringBoot(): void
    {
        $this->createTestModule('disabled-module', [
            'name' => 'disabled-module',
            'version' => '1.0.0',
            'entry' => 'DisabledModule\\Module',
        ], <<<'PHP'
<?php
namespace DisabledModule;
use VelvetCMS\Core\BaseModule;
use VelvetCMS\Core\Application;

class Module extends BaseModule
{
    public function register(Application $app): void
    {
        $app->instance('disabled-loaded', true);
    }
}
PHP
        );

        $compiled = [
            'modules' => [
                [
                    'name' => 'disabled-module',
                    'version' => '1.0.0',
                    'entry' => 'DisabledModule\\Module',
                    'path' => $this->testBasePath . '/modules/disabled-module',
                    'enabled' => false,
                ],
            ],
        ];

        file_put_contents(
            $this->testBasePath . '/storage/modules-compiled.json',
            json_encode($compiled, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->testBasePath . '/storage/modules-autoload.php',
            "<?php\nreturn " . var_export([
                'psr-4' => [
                    'DisabledModule\\' => $this->testBasePath . '/modules/disabled-module/src',
                ]
            ], true) . ";"
        );

        $manager = new ModuleManager($this->app);
        $manager->load()->register()->boot();

        $this->assertFalse($this->app->has('disabled-loaded'));
    }
    
    public function testResolveLoadOrderDetectsCircularDependency(): void
    {
        $modules = [
            'module-a' => [
                'name' => 'module-a',
                'version' => '1.0.0',
                'requires' => ['module-b' => '^1.0'],
            ],
            'module-b' => [
                'name' => 'module-b',
                'version' => '1.0.0',
                'requires' => ['module-a' => '^1.0'],
            ],
        ];
        
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Circular dependency');
        
        $this->moduleManager->resolveLoadOrder($modules);
    }
    
    public function testLoadAndBootModulesInOrder(): void
    {
        // Create two test modules with dependencies
        $this->createTestModule('base-module', [
            'name' => 'base-module',
            'version' => '1.0.0',
            'entry' => 'BaseModule\\BaseModule',
        ], <<<'PHP'
<?php
namespace BaseModule;
use VelvetCMS\Core\BaseModule as CoreBaseModule;
use VelvetCMS\Core\Application;

class BaseModule extends CoreBaseModule {
    public function register(Application $app): void {
        $app->instance('base-loaded', true);
    }
    
    public function boot(Application $app): void {
        $app->instance('base-booted', true);
    }
}
PHP
        );
        
        $this->createTestModule('dependent-module', [
            'name' => 'dependent-module',
            'version' => '1.0.0',
            'entry' => 'DependentModule\\DependentModule',
            'requires' => ['base-module' => '^1.0'],
        ], <<<'PHP'
<?php
namespace DependentModule;
use VelvetCMS\Core\BaseModule as CoreBaseModule;
use VelvetCMS\Core\Application;

class DependentModule extends CoreBaseModule {
    public function register(Application $app): void {
        $app->instance('dependent-loaded', true);
    }
    
    public function boot(Application $app): void {
        $app->instance('dependent-booted', true);
    }
}
PHP
        );
        
        // Create compiled manifest
        $compiled = [
            'modules' => [
                [
                    'name' => 'base-module',
                    'version' => '1.0.0',
                    'entry' => 'BaseModule\\BaseModule',
                    'path' => $this->testBasePath . '/modules/base-module',
                    'enabled' => true,
                ],
                [
                    'name' => 'dependent-module',
                    'version' => '1.0.0',
                    'entry' => 'DependentModule\\DependentModule',
                    'path' => $this->testBasePath . '/modules/dependent-module',
                    'enabled' => true,
                    'requires' => ['base-module' => '^1.0'],
                ],
            ],
        ];
        
        file_put_contents(
            $this->testBasePath . '/storage/modules-compiled.json',
            json_encode($compiled, JSON_PRETTY_PRINT)
        );
        
        // Create autoloader
        $autoload = [
            'psr-4' => [
                'BaseModule\\' => $this->testBasePath . '/modules/base-module/src',
                'DependentModule\\' => $this->testBasePath . '/modules/dependent-module/src',
            ]
        ];
        
        file_put_contents(
            $this->testBasePath . '/storage/modules-autoload.php',
            "<?php\nreturn " . var_export($autoload, true) . ";"
        );
        
        // Load and boot modules
        $manager = new ModuleManager($this->app);
        $manager->load()->register()->boot();
        
        // Verify both modules loaded and booted
        $this->assertTrue($this->app->has('base-loaded'));
        $this->assertTrue($this->app->has('base-booted'));
        $this->assertTrue($this->app->has('dependent-loaded'));
        $this->assertTrue($this->app->has('dependent-booted'));
    }
    
    public function testModuleEnableDisableCycle(): void
    {
        // Create test module
        $this->createTestModule('toggle-module', [
            'name' => 'toggle-module',
            'version' => '1.0.0',
            'entry' => 'TestModule\\ToggleModule',
        ]);
        
        $statePath = $this->testBasePath . '/storage/modules.json';
        
        // Initially no state file
        $this->assertFileDoesNotExist($statePath);
        
        // Enable module
        $state = ['enabled' => ['toggle-module']];
        file_put_contents($statePath, json_encode($state));
        
        $this->assertFileExists($statePath);
        $loaded = json_decode(file_get_contents($statePath), true);
        $this->assertContains('toggle-module', $loaded['enabled']);
        
        // Disable module
        $state = ['enabled' => []];
        file_put_contents($statePath, json_encode($state));
        
        $loaded = json_decode(file_get_contents($statePath), true);
        $this->assertNotContains('toggle-module', $loaded['enabled']);
    }

    public function testManifestNeedsRecompileWhenDependencyVersionChanges(): void
    {
        $this->createTestModule('core-module', [
            'name' => 'core-module',
            'version' => '1.0.0',
            'entry' => 'CoreModule\\Module',
        ]);

        $this->createTestModule('dependent-module', [
            'name' => 'dependent-module',
            'version' => '1.0.0',
            'entry' => 'DependentModule\\Module',
            'requires' => ['core-module' => '^1.0'],
        ]);

        $discovered = $this->moduleManager->discover();
        $issues = $this->moduleManager->validate('dependent-module', $discovered['dependent-module'], $discovered);
        $this->assertSame([], $issues);

        // Simulate version change that violates constraint
        $discovered['core-module']['version'] = '2.0.0';
        $issuesAfterChange = $this->moduleManager->validate('dependent-module', $discovered['dependent-module'], $discovered);

        $this->assertNotEmpty($issuesAfterChange);
        $this->assertStringContainsString('core-module', implode(' ', $issuesAfterChange));
    }
    
    private function createTestModule(string $name, array $manifest, ?string $entryClass = null): void
    {
        $modulePath = $this->testBasePath . '/modules/' . $name;
        mkdir($modulePath, 0755, true);
        mkdir($modulePath . '/src', 0755, true);
        
        // Create module.json
        file_put_contents(
            $modulePath . '/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
        
        // Create entry class if provided
        if ($entryClass) {
            $parts = explode('\\', $manifest['entry']);
            $className = array_pop($parts);
            
            file_put_contents(
                $modulePath . '/src/' . $className . '.php',
                $entryClass
            );
        }
    }
    
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
