<?php

/**
 * Cache Configuration
 *
 * Configure your cache driver and settings here.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | Supported: "file", "redis", "apcu", "array"
    |
    | "file" - File-based caching (default, works everywhere)
    | "redis" - Redis caching (recommended for production)
    | "apcu" - APCu in-memory caching (single server only)
    | "array" - In-memory array (for testing, not persistent)
    |
    */
    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
            'prefix' => 'cache_',
        ],

        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_CACHE_DB', 1),
            'prefix' => env('CACHE_PREFIX', 'zephyr_cache_'),
            'timeout' => 2.0,
        ],

        'apcu' => [
            'driver' => 'apcu',
            'prefix' => env('CACHE_PREFIX', 'zephyr_cache_'),
        ],

        'array' => [
            'driver' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | A global prefix for all cache keys to avoid collisions.
    |
    */
    'prefix' => env('CACHE_PREFIX', 'zephyr_'),
];
