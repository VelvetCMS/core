<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
