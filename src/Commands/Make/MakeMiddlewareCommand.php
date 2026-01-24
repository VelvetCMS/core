<?php

declare(strict_types=1);

namespace VelvetCMS\Commands\Make;

class MakeMiddlewareCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:middleware {name}';
    }

    public function description(): string
    {
        return 'Create a new middleware class';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        return $this->generateClass(
            $this->argument(0),
            'VelvetCMS\\Http\\Middleware',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

class {{ class }}
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
PHP
        );
    }
}
