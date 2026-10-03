<?php

/**
 * QuickQR SaaS - Production Cron Maintenance Script
 *
 * Suggested Cron Schedule:
 * 0 3 * * * php /path/to/scripts/cron-cleanup.php >> /path/to/storage/logs/cron.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    die("Error: This script must be run from the command line (CLI).\n");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Database;
use App\Config\Config;

Config::load();

$startTime = microtime(true);
$dateStr = date('Y-m-d H:i:s');
echo "[{$dateStr}] Starting QuickQR SaaS daily cleanup...\n";

// 1. Clean expired password reset tokens
try {
    $clearedTokens = Database::execute(
        "UPDATE users 
         SET reset_token = NULL, reset_token_expires_at = NULL 
         WHERE reset_token IS NOT NULL AND reset_token_expires_at < NOW()"
    );
    echo "  - Cleared {$clearedTokens} expired password reset tokens.\n";
} catch (Throwable $e) {
    echo "  - [WARN] Failed to clear reset tokens: " . $e->getMessage() . "\n";
}

// 2. Clean expired file cache entries in storage/cache
$cacheDir = dirname(__DIR__) . '/storage/cache';
$expiredCacheCount = 0;
if (is_dir($cacheDir)) {
    $now = time();
    $files = glob($cacheDir . '/cache_*.json');
    if ($files) {
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content) {
                $data = @json_decode($content, true);
                if (isset($data['expires_at']) && $data['expires_at'] < $now) {
                    @unlink($file);
                    $expiredCacheCount++;
                }
            }
        }
    }
}
echo "  - Cleaned {$expiredCacheCount} expired cache files.\n";

// 3. Mark expired QR codes based on campaign expiration dates
try {
    $expiredQrs = Database::execute(
        "UPDATE qr_codes 
         SET status = 'expired' 
         WHERE status = 'active' 
           AND expires_at IS NOT NULL 
           AND expires_at <= NOW()"
    );
    echo "  - Marked {$expiredQrs} dynamic QR campaigns as expired.\n";
} catch (Throwable $e) {
    echo "  - [WARN] Failed to update expired QR codes: " . $e->getMessage() . "\n";
}

$elapsed = round(microtime(true) - $startTime, 3);
echo "[{$dateStr}] Cleanup completed in {$elapsed}s.\n\n";
