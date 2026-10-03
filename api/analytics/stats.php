<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrCode;
use App\Models\QrScan;

$user = AuthMiddleware::requireAuth();

$qrId = Request::input('qr_id');

if (!empty($qrId)) {
    $isAdmin = ($user['role'] ?? '') === 'admin';
    $userId = $isAdmin ? null : (int)$user['id'];
    $qr = QrCode::findById($qrId, $userId);
    if (!$qr) {
        Response::notFound('QR Code not found or access denied.');
    }

    $days = max(1, min(365, (int)Request::input('days', 30)));
    $stats = QrScan::getStatsForQr($qrId, $days);

    Response::success([
        'qr'    => [
            'id'         => $qr['id'],
            'name'       => $qr['name'],
            'short_code' => $qr['short_code'],
            'type'       => $qr['type'],
            'status'     => $qr['status'],
        ],
        'stats' => $stats,
    ]);
} else {
    $overview = QrScan::getUserOverviewStats($user['id']);
    Response::success($overview);
}