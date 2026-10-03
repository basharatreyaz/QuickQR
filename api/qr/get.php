<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrCode;
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

Response::success(QrService::formatQrPayload($qr));