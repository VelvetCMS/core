<?php

declare(strict_types=1);

/**
 * Application Configuration
 */
return [
    /** Application name */
    'name' => env('APP_NAME', 'VelvetCMS'),

    /** Application URL (without trailing slash) */
    'url' => env('APP_URL', 'http://localhost'),

    /** Environment label (local or production). Any non-production value is treated as local. */
    'env' => env('APP_ENV', 'production'),

    /** Enable debug mode (detailed HTML/JSON errors). Defaults to true outside production. */
    'debug' => env('APP_DEBUG', env('APP_ENV', 'production') !== 'production'),

    /** Logging level: debug, info, notice, warning, error, critical, alert, emergency */
    'log_level' => env('APP_LOG_LEVEL', 'info'),

    /** Default timezone */
    'timezone' => 'UTC',

    /** Default locale */
    'locale' => 'en',
];
