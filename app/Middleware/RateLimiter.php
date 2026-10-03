<?php

namespace App\Middleware;

use App\Cache\Cache;
use App\Database\Database;
use App\Helpers\Request;
use App\Helpers\Response;

class RateLimiter
{
    public static function check(string $prefix, int $maxAttempts, int $decaySeconds, ?string $key = null): void
    {
        $identifier = $key ?? Request::ip();

        // 1. Fast In-Memory Cache (Redis / Memory)
        if (Cache::isAvailable()) {
            $cacheKey = "rate:{$prefix}:{$identifier}";
            $hits = Cache::increment($cacheKey, 1, $decaySeconds);

            if ($hits > $maxAttempts) {
                header("Retry-After: {$decaySeconds}");
                Response::rateLimited("Rate limit exceeded. Please try again in {$decaySeconds} seconds.");
            }
            return;
        }

        // 2. Fallback to MySQL if cache driver is unavailable
        $rateKey = hash('sha256', "rate:{$prefix}:{$identifier}");
        $now = time();

        // Garbage collection of expired keys occasionally
        if (random_int(1, 100) === 1) {
            Database::execute("DELETE FROM rate_limits WHERE expires_at < ?", [$now]);
        }

        $row = Database::fetchOne("SELECT hits, expires_at FROM rate_limits WHERE rate_key = ?", [$rateKey]);

        if (!$row) {
            Database::execute(
                "INSERT INTO rate_limits (rate_key, hits, expires_at) VALUES (?, 1, ?)",
                [$rateKey, $now + $decaySeconds]
            );
            return;
        }

        if ($row['expires_at'] < $now) {
            Database::execute(
                "UPDATE rate_limits SET hits = 1, expires_at = ? WHERE rate_key = ?",
                [$now + $decaySeconds, $rateKey]
            );
            return;
        }

        if ((int)$row['hits'] >= $maxAttempts) {
            $retryAfter = $row['expires_at'] - $now;
            header("Retry-After: {$retryAfter}");
            Response::rateLimited("Rate limit exceeded. Please try again in {$retryAfter} seconds.");
        }

        Database::execute("UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?", [$rateKey]);
    }
}
