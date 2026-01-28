<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Make;

class MakeModuleCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:module {name}';
    }

    public function description(): string
    {
        return 'Create a new module structure';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Module name is required.');
            return 1;
        }

        $moduleName = $name;
        $className = $this->formatClassName($name);

        $basePath = base_path("user/modules/{$moduleName}");

        if (is_dir($basePath)) {
            $this->error("Module '{$moduleName}' already exists.");
            return 1;
        }

        $this->info("Creating module structure for {$moduleName}...");
        mkdir($basePath, 0755, true);
        mkdir("{$basePath}/src", 0755, true);

        $manifest = [
            'name' => strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $moduleName)),
            'version' => '1.0.0',
            'path' => '.',
            'entry' => "{$className}\\Module",
            'description' => "The {$moduleName} module.",
            'requires' => [
                'core' => '>=1.0.0'
            ],
            'autoload' => [
                'psr-4' => [
                    "{$className}\\" => 'src/'
                ]
            ]
        ];

        file_put_contents(
            "{$basePath}/module.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $stub = <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use VelvetCMS\Core\BaseModule;
use VelvetCMS\Core\Application;

class Module extends BaseModule
{
    public function boot(Application $app): void
    {
    }

    public function register(Application $app): void
    {
    }
}
PHP;

        $content = $this->replacePlaceholders($stub, [
            '{{ namespace }}' => $className,
        ]);

        file_put_contents("{$basePath}/src/Module.php", $content);

        $composer = [
            'name' => 'velvet-modules/' . strtolower($moduleName),
            'description' => $manifest['description'],
            'type' => 'velvetcms-module',
            'autoload' => $manifest['autoload']
        ];

        file_put_contents(
            "{$basePath}/composer.json",
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->success("Module [{$moduleName}] created successfully in user/modules/{$moduleName}.");
        $this->info("Run 'php velvet module:enable {$manifest['name']}' to activate it.");

        return 0;
    }
}
