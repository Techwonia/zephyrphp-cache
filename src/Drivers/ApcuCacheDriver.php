<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache\Drivers;

use ZephyrPHP\Cache\CacheInterface;

/**
 * APCu Cache Driver
 *
 * Uses APCu for in-memory caching (single server only).
 */
class ApcuCacheDriver implements CacheInterface
{
    private string $prefix;
    private bool $available;

    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'zephyr_cache_';
        $this->available = function_exists('apcu_fetch') && apcu_enabled();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->available) {
            return $default;
        }

        $success = false;
        $value = apcu_fetch($this->prefix . $key, $success);

        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->available) {
            return false;
        }

        return apcu_store($this->prefix . $key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        return apcu_delete($this->prefix . $key);
    }

    public function clear(): bool
    {
        if (!$this->available) {
            return false;
        }

        // Clear only keys with our prefix
        $info = apcu_cache_info();
        if (isset($info['cache_list'])) {
            foreach ($info['cache_list'] as $item) {
                $key = $item['info'] ?? $item['key'] ?? '';
                if (str_starts_with($key, $this->prefix)) {
                    apcu_delete($key);
                }
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (!$this->available) {
            return false;
        }

        return apcu_exists($this->prefix . $key);
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefix . $key] = $value;
        }

        $failures = apcu_store($prefixedValues, null, $ttl);

        return empty($failures);
    }

    public function deleteMultiple(array $keys): bool
    {
        if (!$this->available) {
            return false;
        }

        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        return apcu_delete($prefixedKeys) !== false;
    }

    /**
     * Check if APCu is available
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }
}
