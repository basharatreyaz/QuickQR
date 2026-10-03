<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrCode;
use App\Models\User;
use App\Services\QrService;

$user = AuthMiddleware::requireAuth();

$id = (int)Request::input('id');
if (!$id) {
    Response::validationError('QR Code ID is required.');
}

$isAdmin = ($user['role'] ?? '') === 'admin';
$userId = $isAdmin ? null : (int)$user['id'];

$qr = QrCode::findById($id, $userId);
if (!$qr) {
    Response::notFound('QR Code not found.');
}

$format = strtolower(Request::input('format', 'png'));
if (!in_array($format, ['png', 'svg'], true)) {
    $format = 'png';
}

$plan = User::getActivePlan($user['id']);
if ($format === 'svg' && !$isAdmin && empty($plan['allow_svg'])) {
    Response::forbidden('SVG vector downloads are reserved for Pro and Business plans.');
}

$config = is_string($qr['config']) ? (json_decode($qr['config'], true) ?: []) : ($qr['config'] ?? []);

$content = QrService::getRedirectUrl($qr['short_code']);
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $qr['name']) . "-{$qr['short_code']}.{$format}";

try {
    $rendered = QrService::generatePreview($content, $config, $format);

    if ($format === 'svg') {
        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($rendered));
        echo $rendered;
        exit;
    }

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($rendered));
    echo $rendered;
    exit;
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'DOWNLOAD_ERROR', 500);
}