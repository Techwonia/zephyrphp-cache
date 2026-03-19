<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache;

/**
 * Cache Interface
 *
 * Defines the contract for all cache drivers.
 */
interface CacheInterface
{
    /**
     * Get an item from the cache
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The cached value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store an item in the cache
     *
     * @param string $key The cache key
     * @param mixed $value The value to store
     * @param int $ttl Time to live in seconds (0 = forever)
     * @return bool True on success
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Remove an item from the cache
     *
     * @param string $key The cache key
     * @return bool True on success
     */
    public function delete(string $key): bool;

    /**
     * Clear all items from the cache
     *
     * @return bool True on success
     */
    public function clear(): bool;

    /**
     * Check if an item exists in the cache
     *
     * @param string $key The cache key
     * @return bool True if exists and not expired
     */
    public function has(string $key): bool;

    /**
     * Get multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Store multiple items in the cache
     *
     * @param array $values Associative array of key => value
     * @param int $ttl Time to live in seconds
     * @return bool True on success
     */
    public function setMultiple(array $values, int $ttl = 0): bool;

    /**
     * Remove multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return bool True on success
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Add an item only if it doesn't already exist (atomic)
     *
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds (0 = forever)
     * @return bool True if added, false if already exists
     */
    public function add(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Atomically increment a numeric cache value
     *
     * @param string $key Cache key
     * @param int $amount Amount to increment
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $amount = 1): int|false;

    /**
     * Atomically decrement a numeric cache value
     *
     * @param string $key Cache key
     * @param int $amount Amount to decrement
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $amount = 1): int|false;
}
