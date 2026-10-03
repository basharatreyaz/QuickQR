<?php

namespace App\Cache;

use App\Config\Config;
use Throwable;

class FileDriver implements CacheInterface
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? (dirname(__DIR__, 2) . '/storage/cache/');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->cacheDir . 'cache_' . $hash . '.json';
    }

    public function isAvailable(): bool
    {
        return is_dir($this->cacheDir) && is_writable($this->cacheDir);
    }

    public function getName(): string
    {
        return 'file';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return $default;
        }

        try {
            $content = @file_get_contents($file);
            if ($content === false) {
                return $default;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || !isset($data['expires_at'])) {
                @unlink($file);
                return $default;
            }

            if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
                @unlink($file);
                return $default;
            }

            return $data['value'];
        } catch (Throwable $e) {
            return $default;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $payload = [
            'expires_at' => ($ttl > 0) ? (time() + $ttl) : 0,
            'value'      => $value,
        ];

        try {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $tmpFile = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmpFile, $json) !== false) {
                if (file_exists($file)) {
                    @unlink($file);
                }
                return @rename($tmpFile, $file);
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function increment(string $key, int $value = 1, int $ttl = 60): int
    {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new, $ttl);
        return $new;
    }

    public function flush(): bool
    {
        $files = glob($this->cacheDir . 'cache_*.json');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        return true;
    }
}
