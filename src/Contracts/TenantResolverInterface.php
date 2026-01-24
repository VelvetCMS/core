<?php

declare(strict_types=1);

namespace VelvetCMS\Contracts;

use VelvetCMS\Core\Tenancy\TenantContext;
use VelvetCMS\Http\Request;

interface TenantResolverInterface
{
    /**
     * Resolve tenant context from request and tenancy config.
     */
    public function resolve(Request $request, array $config): ?TenantContext;
}
