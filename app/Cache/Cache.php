<?php

namespace App\Cache;

use App\Config\Config;
use Throwable;

class Cache
{
    private static ?CacheInterface $driver = null;

    public static function getDriver(): CacheInterface
    {
        if (self::$driver !== null) {
            return self::$driver;
        }

        Config::load();
        $preferred = strtolower((string)Config::get('CACHE_DRIVER', 'redis'));

        if ($preferred === 'redis') {
            $redis = new RedisDriver();
            if ($redis->isAvailable()) {
                self::$driver = $redis;
                return self::$driver;
            }
        }

        if ($preferred !== 'none') {
            $file = new FileDriver();
            if ($file->isAvailable()) {
                self::$driver = $file;
                return self::$driver;
            }
        }

        // Null driver fallback
        self::$driver = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed { return $default; }
            public function set(string $key, mixed $value, int $ttl = 3600): bool { return true; }
            public function has(string $key): bool { return false; }
            public function delete(string $key): bool { return true; }
            public function increment(string $key, int $value = 1, int $ttl = 60): int { return $value; }
            public function flush(): bool { return true; }
            public function isAvailable(): bool { return false; }
            public function getName(): string { return 'none'; }
        };

        return self::$driver;
    }

    public static function isAvailable(): bool
    {
        return self::getDriver()->isAvailable();
    }

    public static function isRedis(): bool
    {
        return self::getDriver()->getName() === 'redis';
    }

    public static function getDriverName(): string
    {
        return self::getDriver()->getName();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getDriver()->get($key, $default);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::getDriver()->set($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        return self::getDriver()->has($key);
    }

    public static function delete(string $key): bool
    {
        return self::getDriver()->delete($key);
    }

    public static function increment(string $key, int $value = 1, int $ttl = 60): int
    {
        return self::getDriver()->increment($key, $value, $ttl);
    }

    public static function flush(): bool
    {
        return self::getDriver()->flush();
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $callback();
        self::set($key, $fresh, $ttl);
        return $fresh;
    }
}
