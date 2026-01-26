<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache;

use ZephyrPHP\Container\Container;

class CacheServiceProvider
{
    public function register(Container $container): void
    {
        // Register CacheManager as singleton
        $container->singleton(CacheManager::class, function () {
            return CacheManager::getInstance();
        });

        // Register alias
        $container->alias('cache', CacheManager::class);
    }

    public function boot(): void
    {
        // Initialize the cache manager
        CacheManager::getInstance();
    }
}
