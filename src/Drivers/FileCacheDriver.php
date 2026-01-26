<?php

declare(strict_types=1);

namespace ZephyrPHP\Cache\Drivers;

use ZephyrPHP\Cache\CacheInterface;

/**
 * File-based Cache Driver
 *
 * Stores cache data as serialized files on disk.
 */
class FileCacheDriver implements CacheInterface
{
    private string $path;
    private string $prefix;

    public function __construct(array $config = [])
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $this->path = $config['path'] ?? $basePath . '/storage/cache';
        $this->prefix = $config['prefix'] ?? 'cache_';

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = @unserialize($content);
        if ($data === false) {
            unlink($file);
            return $default;
        }

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->path . '/' . $this->prefix . '*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

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
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get the file path for a cache key
     */
    private function getFilePath(string $key): string
    {
        return $this->path . '/' . $this->prefix . md5($key) . '.cache';
    }

    /**
     * Clean up expired cache files
     */
    public function gc(): int
    {
        $files = glob($this->path . '/' . $this->prefix . '*.cache');
        $cleaned = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = @unserialize($content);
            if ($data === false || ($data['expires'] !== 0 && $data['expires'] < time())) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
