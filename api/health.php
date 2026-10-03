<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;
use App\Cache\Cache;
use App\Helpers\Response;

$startTime = microtime(true);
$checks = [
    'database' => false,
    'cache'    => false,
    'storage'  => false,
];
$errors = [];

// 1. Database Check
try {
    $row = Database::fetchOne('SELECT 1 as ping');
    if ($row && (int)$row['ping'] === 1) {
        $checks['database'] = true;
    } else {
        $errors['database'] = 'Unexpected query result';
    }
} catch (Throwable $e) {
    $errors['database'] = 'Database connection failed';
}

// 2. Cache Check
try {
    $testKey = 'health_ping_' . bin2hex(random_bytes(4));
    Cache::set($testKey, 'ok', 10);
    $val = Cache::get($testKey);
    Cache::delete($testKey);
    if ($val === 'ok') {
        $checks['cache'] = true;
    } else {
        $errors['cache'] = 'Cache read/write test failed';
    }
} catch (Throwable $e) {
    $errors['cache'] = 'Cache error: ' . $e->getMessage();
}

// 3. Storage Directory Writability Check
$storageDirs = [
    'logs'    => dirname(__DIR__) . '/storage/logs',
    'cache'   => dirname(__DIR__) . '/storage/cache',
    'uploads' => dirname(__DIR__) . '/storage/uploads',
];
$storageWritable = true;
foreach ($storageDirs as $name => $dir) {
    if (!is_dir($dir) || !is_writable($dir)) {
        $storageWritable = false;
        $errors['storage'] = "Directory storage/{$name} is not writable";
        break;
    }
}
$checks['storage'] = $storageWritable;

$isHealthy = $checks['database'] && $checks['cache'] && $checks['storage'];
$responseTimeMs = round((microtime(true) - $startTime) * 1000, 2);

$payload = [
    'status'       => $isHealthy ? 'healthy' : 'unhealthy',
    'timestamp'    => date('c'),
    'environment'  => Config::get('APP_ENV', 'local'),
    'php_version'  => PHP_VERSION,
    'response_ms'  => $responseTimeMs,
    'checks'       => $checks,
];

if (!empty($errors)) {
    $payload['errors'] = $errors;
}

Response::json($payload, $isHealthy ? 200 : 503);
