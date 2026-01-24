<?php

declare(strict_types=1);

namespace VelvetCMS\Exceptions;

use VelvetCMS\Http\Request;
use VelvetCMS\Http\Response;

interface RenderableExceptionInterface
{
    public function toResponse(Request $request): Response;
}
