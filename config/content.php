<?php

declare(strict_types=1);

/**
 * Content Configuration
 */
return [
    /** Default content driver: file, db, hybrid, auto */
    'driver' => env('CONTENT_DRIVER', 'file'),
    
    /** Content driver configurations */
    'drivers' => [
        /** Hybrid: metadata in DB, content in files */
        'hybrid' => [
            'content' => 'file',
            'metadata' => 'db',
            'cache_ttl' => 300,
        ],
        
        /** File: Markdown files with frontmatter */
        'file' => [
            'path' => content_path('pages'),
            'cache_enabled' => true,
            'cache_ttl' => 600,
        ],
        
        /** Database: All content in database */
        'db' => [
            'table' => 'pages',
            'cache_ttl' => 300,
        ],
        
        /** Auto: Switches driver based on page count threshold */
        'auto' => [
            'threshold' => 100,
            'small_site' => 'file',
            'large_site' => 'hybrid',
        ],
    ],
    
    /** Content parsing configuration */
    'parser' => [
        /** 
         * Parser driver: commonmark, parsedown, html
         * Custom drivers can be bound to VelvetCMS\Contracts\ParserInterface
         */
        'driver' => env('CONTENT_PARSER_DRIVER', 'commonmark'),

        /** Parsed content cache TTL in seconds (0 = disabled) */
        'cache_ttl' => 600,
        
        /** Driver-specific configurations */
        'drivers' => [
            'commonmark' => [
                /** Allow raw HTML in markdown */
                'html_input' => 'allow',
                /** CommonMark extensions */
                'extensions' => [
                    'table' => true,
                    'strikethrough' => true,
                    'autolink' => true,
                    'task_lists' => true,
                ],
            ],
            
            'parsedown' => [
                /** strip = safe mode */
                'html_input' => 'allow',
                'breaks' => true,
            ],
            
            'html' => [],
        ],
    ],
];