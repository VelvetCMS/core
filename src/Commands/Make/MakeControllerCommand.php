<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Make;

class MakeControllerCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:controller {name}';
    }

    public function description(): string
    {
        return 'Create a new controller class';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        return $this->generateClass(
            $this->argument(0),
            'VelvetCMS\\Http\\Controllers',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class {{ class }}
{
    public function index(Request $request): Response
    {
        return Response::html('Hello from {{ class }}');
    }
}
PHP
        );
    }
}
