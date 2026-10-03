<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrScan;
use App\Models\AuditLog;

AuthMiddleware::requireAdmin();

$stats = QrScan::getAdminOverviewStats();
$recentLogs = AuditLog::getRecent(15);

Response::success([
    'stats'       => $stats,
    'recent_logs' => $recentLogs,
]);