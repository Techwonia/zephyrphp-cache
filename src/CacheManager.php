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
     * Validate a cache key
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Cache key must not be empty.');
        }
        if (strlen($key) > 250) {
            throw new \InvalidArgumentException('Cache key must not exceed 250 characters.');
        }
        if (preg_match('/[\x00-\x1f\x7f{}()\/@\\\\]/', $key)) {
            throw new \InvalidArgumentException("Cache key contains invalid characters: {$key}");
        }
    }

    /**
     * Create a cache driver instance
     */
    private function createDriver(string $driver): CacheInterface
    {
        $storeConfig = $this->config['stores'][$driver] ?? [];

        if ($driver === 'redis' || $driver === 'apcu') {
            try {
                return match ($driver) {
                    'redis' => new RedisCacheDriver($storeConfig),
                    'apcu' => new ApcuCacheDriver($storeConfig),
                };
            } catch (\Throwable $e) {
                // Fall back to file cache if Redis/APCu fails to initialize
                $fileConfig = $this->config['stores']['file'] ?? [];
                return new FileCacheDriver($fileConfig);
            }
        }

        return match ($driver) {
            'file' => new FileCacheDriver($storeConfig),
            'redis' => new RedisCacheDriver($storeConfig),
            'apcu' => new ApcuCacheDriver($storeConfig),
            'array' => new ArrayCacheDriver(),
            default => throw new \InvalidArgumentException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * Get an item from the default cache store
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        return $this->store()->get($key, $default);
    }

    /**
     * Store an item in the default cache store
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->validateKey($key);
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * Delete an item from the default cache store
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
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
        $this->validateKey($key);
        return $this->store()->has($key);
    }

    /**
     * Get multiple items from the default cache store
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * Store multiple items in the default cache store
     */
    public function setMultiple(array $values, int $ttl = 0): bool
    {
        foreach (array_keys($values) as $key) {
            $this->validateKey((string) $key);
        }
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * Delete multiple items from the default cache store
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }
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
        $this->validateKey($key);

        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        // Use a lock key to mitigate cache stampede (thundering herd)
        $lockKey = $key . ':_lock';
        $maxRetries = 10;
        $retryDelay = 50000; // 50ms

        if ($this->has($lockKey)) {
            for ($i = 0; $i < $maxRetries; $i++) {
                usleep($retryDelay);
                $value = $this->get($key);
                if ($value !== null) {
                    return $value;
                }
                if (!$this->has($lockKey)) {
                    break;
                }
            }
        }

        $this->set($lockKey, '1', 30);
        try {
            $value = $callback();
            $this->set($key, $value, $ttl);
        } finally {
            $this->delete($lockKey);
        }

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
        $this->validateKey($key);
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
        $this->validateKey($key);
        $value = $this->store()->get($key, $default);
        $this->store()->delete($key);
        return $value;
    }

    /**
     * Increment a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to increment
     * @return int|bool New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $this->validateKey($key);
        return $this->store()->increment($key, $value);
    }

    /**
     * Decrement a numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        $this->validateKey($key);
        return $this->store()->decrement($key, $value);
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
        $this->validateKey($key);
        return $this->store()->add($key, $value, $ttl);
    }

    /**
     * Store an item in the cache indefinitely
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @return bool True on success
     */
    public function forever(string $key, mixed $value): bool
    {
        $this->validateKey($key);
        return $this->store()->set($key, $value, 0);
    }

    /**
     * Flush all cache stores
     */
    public function flush(): bool
    {
        foreach (array_keys($this->config['stores'] ?? []) as $name) {
            $this->store($name)->clear();
        }

        return true;
    }
}
