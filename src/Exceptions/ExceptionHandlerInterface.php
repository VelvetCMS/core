<?php

declare(strict_types=1);

namespace VelvetCMS\Exceptions;

use Throwable;
use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

interface ExceptionHandlerInterface
{
    public function report(Throwable $e, Request $request): void;

    public function render(Throwable $e, Request $request): Response;
}
