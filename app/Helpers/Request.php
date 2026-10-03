<?php

namespace App\Helpers;

class Request
{
    private static ?array $jsonData = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function getJson(): array
    {
        if (self::$jsonData !== null) {
            return self::$jsonData;
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            self::$jsonData = [];
            return self::$jsonData;
        }

        $decoded = json_decode($raw, true);
        self::$jsonData = is_array($decoded) ? $decoded : [];
        return self::$jsonData;
    }

    public static function json(): array
    {
        return self::getJson();
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $json = self::getJson();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function all(): array
    {
        $json = self::getJson();
        return array_merge($_GET, $_POST, $json);
    }

    public static function header(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return trim($_SERVER[$serverKey]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, $name) === 0) {
                    return trim($v);
                }
            }
        }

        return null;
    }

    public static function bearerToken(): ?string
    {
        $auth = self::header('Authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }

        $apiKey = self::header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        return null;
    }

    public static function ip(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Check if proxy headers should be trusted (e.g., behind Cloudflare, Docker bridge, or Nginx)
        $isPrivateOrLocal = filter_var(
            $remoteAddr,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;

        $trustProxies = $isPrivateOrLocal || (bool)\App\Config\Config::get('TRUST_PROXIES', false);

        if ($trustProxies) {
            $proxyHeaders = [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_CLIENT_IP',
            ];

            foreach ($proxyHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    $ips = explode(',', $_SERVER[$header]);
                    $candidate = trim($ips[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public static function referrer(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? 'Direct';
    }
}