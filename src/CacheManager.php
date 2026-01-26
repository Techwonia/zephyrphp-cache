<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache;

use ZephyrPHP\Config\Config;
use ZephyrPHP\Cache\Drivers\FileCacheDriver;
use ZephyrPHP\Cache\Drivers\RedisCacheDriver;
use ZephyrPHP\Cache\Drivers\ApcuCacheDriver;
use ZephyrPHP\Cache\Drivers\ArrayCacheDriver;

/**
 * Cache Manager
 *
 * Manages multiple cache stores and provides a unified interface.
 */
class CacheManager implements CacheInterface
{
    private static ?CacheManager $instance = null;
    private string $defaultDriver = 'file';
    private array $stores = [];
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config ?: Config::get('cache', []);
        $this->defaultDriver = $this->config['default'] ?? 'file';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a cache store by name
     *
     * @param string|null $name Store name (null for default)
     * @return CacheInterface
     */
    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?? $this->defaultDriver;

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createDriver($name);
        }

        return $this->stores[$name];
    }

    /**
     * Create a cache driver instance
     */
    private function createDriver(string $driver): CacheInterface
    {
        $storeConfig = $this->config['stores'][$driver] ?? [];

        return match ($driver) {
            'file' => new FileCacheDriver($storeConfig),
            'redis' => new RedisCacheDriver($storeConfig),
            'apcu' => new ApcuCacheDriver($storeConfig),
            'array' => new ArrayCacheDriver(),
            default => new FileCacheDriver($storeConfig),
        };
    }

    /**
     * Get an item from the default cache store
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    /**
     * Store an item in the default cache store
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * Delete an item from the default cache store
     */
    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    /**
     * Clear the default cache store
     */
    public function clear(): bool
    {
        return $this->store()->clear();
    }

    /**
     * Check if an item exists in the default cache store
     */
    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    /**
     * Get multiple items from the default cache store
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * Store multiple items in the default cache store
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * Delete multiple items from the default cache store
     */
    public function deleteMultiple(array $keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    /**
     * Remember a value (get from cache or compute and store)
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to compute value if not cached
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Remember a value forever
     *
     * @param string $key Cache key
     * @param callable $callback Function to compute value if not cached
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, 0, $callback);
    }

    /**
     * Get and delete an item (pull)
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Increment a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to increment
     * @return int|bool New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);

        if (!is_numeric($current)) {
            return false;
        }

        $new = (int) $current + $value;
        $this->set($key, $new);

        return $new;
    }

    /**
     * Decrement a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement
     * @return int|bool New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * Add an item only if it doesn't exist
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live
     * @return bool True if added, false if already exists
     */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * Flush all cache stores
     */
    public function flush(): bool
    {
        foreach ($this->stores as $store) {
            $store->clear();
        }

        return true;
    }
}
