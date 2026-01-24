<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

use VelvetCMS\Core\Application;

interface Module
{
    public function register(Application $app): void;

    public function boot(Application $app): void;

    public function path(string $path = ''): string;
}
