<?php

namespace App\Models;

use App\Cache\Cache;
use App\Database\Database;

class Setting
{
    private static array $memoryCache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }

        $cached = Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $row = Database::fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            return $row ? $row['setting_value'] : $default;
        });

        self::$memoryCache[$key] = $cached;
        return $cached;
    }

    public static function set(string $key, mixed $value): void
    {
        $valStr = is_array($value) ? json_encode($value) : (string)$value;
        Database::execute(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?",
            [$key, $valStr, $valStr]
        );
        self::$memoryCache[$key] = $valStr;
        Cache::delete("setting:{$key}");
        Cache::delete('settings:all');
    }

    public static function getAll(): array
    {
        return Cache::remember('settings:all', 3600, function () {
            $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        });
    }
}
