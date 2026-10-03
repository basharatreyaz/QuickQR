<?php

namespace App\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function increment(string $key, int $value = 1, int $ttl = 60): int;
    public function flush(): bool;
    public function isAvailable(): bool;
    public function getName(): string;
}
