<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache\Drivers;

use ZephyrPHP\Cache\CacheInterface;

/**
 * Array Cache Driver
 *
 * In-memory cache for testing and short-lived data.
 * Data is not persisted between requests.
 */
class ArrayCacheDriver implements CacheInterface
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }

        $data = $this->cache[$key];

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            unset($this->cache[$key]);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
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
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Get all cached keys
     */
    public function keys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * Get the number of cached items
     */
    public function count(): int
    {
        return count($this->cache);
    }
}
