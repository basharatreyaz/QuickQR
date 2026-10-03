<?php

namespace App\Helpers;

use App\Config\Config;

class Security
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateShortCode(int $length = 8): string
    {
        // Alphanumeric, avoiding ambiguous characters (like 0/O, 1/l/I)
        $characters = '23456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, $max)];
        }

        return $code;
    }

    public static function generateApiKey(): string
    {
        return 'qr_' . bin2hex(random_bytes(24));
    }

    public static function hashIp(string $ip): string
    {
        $salt = Config::get('SESSION_SECRET', 'qr_salt_default');
        return hash('sha256', $ip . $salt);
    }

    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return $data;
    }

    public static function validateDestinationUrl(string $url): bool
    {
        $trimmed = trim($url);
        if (empty($trimmed)) {
            return false;
        }

        // Prevent CRLF injection / header splitting
        if (strpbrk($trimmed, "\r\n") !== false) {
            return false;
        }

        // Must not contain dangerous protocol wrappers
        $lower = strtolower($trimmed);
        $forbiddenSchemes = ['javascript:', 'data:', 'file:', 'vbscript:', 'blob:'];
        foreach ($forbiddenSchemes as $bad) {
            if (str_starts_with($lower, $bad)) {
                return false;
            }
        }

        // Validate supported schemes
        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['scheme'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        $allowedSchemes = ['http', 'https', 'mailto', 'tel', 'sms', 'geo', 'facetime'];

        if (!in_array($scheme, $allowedSchemes, true)) {
            return false;
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            if (empty($parsed['host']) || !filter_var($trimmed, FILTER_VALIDATE_URL)) {
                return false;
            }
        }

        return true;
    }
}
