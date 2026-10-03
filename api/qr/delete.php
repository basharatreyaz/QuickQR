<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\QrService;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$user = AuthMiddleware::requireAuth();
$id = (int)Request::input('id');

if (!$id) {
    Response::validationError('QR Code ID is required.');
}

$isAdmin = ($user['role'] ?? '') === 'admin';
$userId = $isAdmin ? null : (int)$user['id'];

try {
    QrService::delete($id, $userId);
    Response::success(null, 'QR Code deleted successfully.');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'SERVER_ERROR', 500);
}