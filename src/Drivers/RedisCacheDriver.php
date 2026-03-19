<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache\Drivers;

use ZephyrPHP\Cache\CacheInterface;

/**
 * Redis Cache Driver
 *
 * Uses Redis for high-performance caching.
 */
class RedisCacheDriver implements CacheInterface
{
    private ?\Redis $redis = null;
    private string $prefix;
    private bool $connected = false;

    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'zephyr_cache_';

        if (extension_loaded('redis')) {
            $this->redis = new \Redis();

            try {
                $host = $config['host'] ?? $_ENV['REDIS_HOST'] ?? '127.0.0.1';
                $port = (int) ($config['port'] ?? $_ENV['REDIS_PORT'] ?? 6379);
                $timeout = $config['timeout'] ?? 2.0;

                $this->connected = $this->redis->connect($host, $port, $timeout);

                // Authenticate if password provided
                $password = $config['password'] ?? $_ENV['REDIS_PASSWORD'] ?? null;
                if ($this->connected && $password) {
                    if (!$this->redis->auth($password)) {
                        $this->connected = false;
                        $this->redis = null;
                    }
                }

                // Select database if specified
                $database = $config['database'] ?? 0;
                if ($this->connected && $database > 0) {
                    $this->redis->select($database);
                }
            } catch (\Exception $e) {
                $this->connected = false;
                $this->redis = null;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->connected || !$this->redis) {
            return $default;
        }

        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            return $default;
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return $decoded !== false ? $decoded : $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        $serialized = serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }

        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function delete(string $key): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function clear(): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        // Delete only keys with our prefix using SCAN (non-blocking)
        $iterator = null;
        do {
            $keys = $this->redis->scan($iterator, $this->prefix . '*', 100);
            if ($keys !== false && !empty($keys)) {
                $this->redis->del(...$keys);
            }
        } while ($iterator > 0);

        return true;
    }

    public function has(string $key): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $values = [];

        if (!$this->connected || !$this->redis) {
            foreach ($keys as $key) {
                $values[$key] = $default;
            }
            return $values;
        }

        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        $results = $this->redis->mget($prefixedKeys);

        foreach ($keys as $i => $key) {
            $value = $results[$i] ?? false;
            $values[$key] = $value !== false ? @unserialize($value, ['allowed_classes' => false]) : $default;
        }

        return $values;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    public function deleteMultiple(array $keys): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        $prefixedKeys = array_map(fn($k) => $this->prefix . $k, $keys);
        return $this->redis->del(...$prefixedKeys) > 0;
    }

    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        $prefixedKey = $this->prefix . $key;
        $args = ['nx'];
        if ($ttl > 0) {
            $args['ex'] = $ttl;
        }

        return (bool) $this->redis->set($prefixedKey, serialize($value), $args);
    }

    public function increment(string $key, int $amount = 1): int|false
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        return $this->redis->incrBy($this->prefix . $key, $amount);
    }

    public function decrement(string $key, int $amount = 1): int|false
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        return $this->redis->decrBy($this->prefix . $key, $amount);
    }

    /**
     * Check if connected to Redis
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the underlying Redis instance
     */
    public function getRedis(): ?\Redis
    {
        return $this->redis;
    }
}
