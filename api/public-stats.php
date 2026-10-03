<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Cache\Cache;
use App\Database\Database;
use App\Helpers\Response;

try {
    $data = Cache::remember('telemetry:public_stats', 15, function () {
        $qrStats = Database::fetchOne("
            SELECT 
                COUNT(CASE WHEN status != 'deleted' THEN 1 END) as total_qrs,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_qrs,
                COALESCE(SUM(scan_count), 0) as total_scans
            FROM qr_codes
        ");

        $todayScans = Database::fetchOne("
            SELECT COUNT(*) as today_scans 
            FROM qr_scans s
            WHERE s.scanned_at >= CURDATE()
        ");

        return [
            'total_qrs'   => (int)($qrStats['total_qrs'] ?? 0),
            'active_qrs'  => (int)($qrStats['active_qrs'] ?? 0),
            'total_scans' => (int)($qrStats['total_scans'] ?? 0),
            'today_scans' => (int)($todayScans['today_scans'] ?? 0),
            'timestamp'   => time(),
        ];
    });

    Response::success($data);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'STATS_ERROR', 500);
}
