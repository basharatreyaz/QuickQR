<?php

namespace App\Cache;

use App\Config\Config;
use Throwable;

class RedisDriver implements CacheInterface
{
    private string $host;
    private int $port;
    private ?string $password;
    private int $database;
    private string $prefix;
    private float $timeout;

    private mixed $nativeClient = null;
    private mixed $socketClient = null;
    private bool $connected = false;
    private bool $failed = false;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $password = null,
        ?int $database = null,
        ?string $prefix = null,
        ?float $timeout = null
    ) {
        $this->host = $host ?? (string)Config::get('REDIS_HOST', '127.0.0.1');
        $this->port = $port ?? (int)Config::get('REDIS_PORT', 6379);
        $this->password = $password ?? (Config::get('REDIS_PASSWORD') ?: null);
        $this->database = $database ?? (int)Config::get('REDIS_DATABASE', 0);
        $this->prefix = $prefix ?? (string)Config::get('REDIS_PREFIX', 'quickqr:');
        $this->timeout = $timeout ?? (float)Config::get('REDIS_TIMEOUT', 0.8);
    }

    private function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        if ($this->failed) {
            return false;
        }

        try {
            // Option 1: Native PECL extension
            if (class_exists('\\Redis')) {
                $redis = new \Redis();
                $ok = @$redis->connect($this->host, $this->port, $this->timeout);
                if ($ok) {
                    if (!empty($this->password)) {
                        @$redis->auth($this->password);
                    }
                    if ($this->database > 0) {
                        @$redis->select($this->database);
                    }
                    $this->nativeClient = $redis;
                    $this->connected = true;
                    return true;
                }
            }

            // Option 2: Pure-PHP stream socket (Zero-dependency, works everywhere)
            $remote = (str_starts_with($this->host, '/') || str_starts_with($this->host, 'unix:'))
                ? 'unix://' . ltrim($this->host, 'unix://')
                : "tcp://{$this->host}:{$this->port}";

            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);

            if (!$socket) {
                $this->failed = true;
                return false;
            }

            stream_set_timeout($socket, (int)$this->timeout, (int)(($this->timeout - (int)$this->timeout) * 1000000));
            $this->socketClient = $socket;

            // Auth if needed
            if (!empty($this->password)) {
                $authRes = $this->sendSocketCommand('AUTH', [$this->password]);
                if ($authRes !== 'OK') {
                    $this->closeSocket();
                    $this->failed = true;
                    return false;
                }
            }

            // Select DB if needed
            if ($this->database > 0) {
                $this->sendSocketCommand('SELECT', [(string)$this->database]);
            }

            $this->connected = true;
            return true;
        } catch (Throwable $e) {
            $this->failed = true;
            $this->closeSocket();
            return false;
        }
    }

    private function closeSocket(): void
    {
        if ($this->socketClient && is_resource($this->socketClient)) {
            @fclose($this->socketClient);
            $this->socketClient = null;
        }
    }

    private function prefixed(string $key): string
    {
        return $this->prefix . $key;
    }

    public function isAvailable(): bool
    {
        return $this->connect();
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->connect()) {
            return $default;
        }

        try {
            $fullKey = $this->prefixed($key);

            if ($this->nativeClient) {
                $val = $this->nativeClient->get($fullKey);
                if ($val === false || $val === null) {
                    return $default;
                }
                return $this->unserialize($val);
            }

            $raw = $this->sendSocketCommand('GET', [$fullKey]);
            if ($raw === null || $raw === false) {
                return $default;
            }
            return $this->unserialize($raw);
        } catch (Throwable $e) {
            return $default;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $fullKey = $this->prefixed($key);
            $serialized = $this->serialize($value);

            if ($this->nativeClient) {
                if ($ttl > 0) {
                    return (bool)$this->nativeClient->setex($fullKey, $ttl, $serialized);
                }
                return (bool)$this->nativeClient->set($fullKey, $serialized);
            }

            if ($ttl > 0) {
                $res = $this->sendSocketCommand('SETEX', [$fullKey, (string)$ttl, $serialized]);
            } else {
                $res = $this->sendSocketCommand('SET', [$fullKey, $serialized]);
            }

            return $res === 'OK';
        } catch (Throwable $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $fullKey = $this->prefixed($key);
            if ($this->nativeClient) {
                return (bool)$this->nativeClient->exists($fullKey);
            }
            $res = $this->sendSocketCommand('EXISTS', [$fullKey]);
            return (int)$res > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $fullKey = $this->prefixed($key);
            if ($this->nativeClient) {
                return (bool)$this->nativeClient->del($fullKey);
            }
            $res = $this->sendSocketCommand('DEL', [$fullKey]);
            return (int)$res > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function increment(string $key, int $value = 1, int $ttl = 60): int
    {
        if (!$this->connect()) {
            return 0;
        }

        try {
            $fullKey = $this->prefixed($key);

            if ($this->nativeClient) {
                $newVal = (int)$this->nativeClient->incrBy($fullKey, $value);
                if ($newVal === $value && $ttl > 0) {
                    $this->nativeClient->expire($fullKey, $ttl);
                }
                return $newVal;
            }

            $newVal = (int)$this->sendSocketCommand('INCRBY', [$fullKey, (string)$value]);
            if ($newVal === $value && $ttl > 0) {
                $this->sendSocketCommand('EXPIRE', [$fullKey, (string)$ttl]);
            }
            return $newVal;
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function flush(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            if ($this->nativeClient) {
                return (bool)$this->nativeClient->flushDB();
            }
            $res = $this->sendSocketCommand('FLUSHDB');
            return $res === 'OK';
        } catch (Throwable $e) {
            return false;
        }
    }

    private function serialize(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function unserialize(string $value): mixed
    {
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }

    /**
     * Send command via raw RESP socket
     */
    private function sendSocketCommand(string $command, array $args = []): mixed
    {
        if (!$this->socketClient || !is_resource($this->socketClient)) {
            return false;
        }

        $all = array_merge([$command], $args);
        $payload = '*' . count($all) . "\r\n";
        foreach ($all as $item) {
            $str = (string)$item;
            $payload .= '$' . strlen($str) . "\r\n" . $str . "\r\n";
        }

        $written = @fwrite($this->socketClient, $payload);
        if ($written === false || $written === 0) {
            $this->closeSocket();
            $this->failed = true;
            return false;
        }

        return $this->readSocketResponse();
    }

    /**
     * Parse RESP response
     */
    private function readSocketResponse(): mixed
    {
        if (!$this->socketClient || feof($this->socketClient)) {
            return false;
        }

        $line = fgets($this->socketClient);
        if ($line === false) {
            return false;
        }

        $type = $line[0];
        $content = substr(rtrim($line, "\r\n"), 1);

        switch ($type) {
            case '+': // Simple string
                return $content;

            case '-': // Error
                return false;

            case ':': // Integer
                return (int)$content;

            case '$': // Bulk string
                $length = (int)$content;
                if ($length < 0) {
                    return null; // Key does not exist
                }
                $data = '';
                $remaining = $length;
                while ($remaining > 0 && !feof($this->socketClient)) {
                    $chunk = fread($this->socketClient, min($remaining, 4096));
                    if ($chunk === false) break;
                    $data .= $chunk;
                    $remaining -= strlen($chunk);
                }
                fread($this->socketClient, 2); // Consume trailing \r\n
                return $data;

            case '*': // Array
                $count = (int)$content;
                if ($count < 0) return null;
                $arr = [];
                for ($i = 0; $i < $count; $i++) {
                    $arr[] = $this->readSocketResponse();
                }
                return $arr;

            default:
                return false;
        }
    }

    public function __destruct()
    {
        $this->closeSocket();
    }
}
