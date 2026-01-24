<?php

declare(strict_types=1);

/**
 * Module Configuration
 */
return [
    /** Paths to scan for modules (supports glob patterns) */
    'paths' => [
        base_path('user/modules/*'),
        base_path('../VelvetCMS-*'),
    ],

    /** Tenant-local module paths (supports {tenant} placeholder) */
    'tenant_paths' => [
        base_path('user/tenants/{tenant}/modules/*'),
    ],

    /**
     * Manually registered modules
     * Example: 'docs' => '../VelvetCMS-Docs'
     */
    'modules' => [],

    /** Auto-discovery from filesystem and composer */
    'auto_discover' => true,
];
