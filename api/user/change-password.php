<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$user = AuthMiddleware::requireAuth();

try {
    AuthService::changePassword($user['id'], Request::all());
    Response::success(null, 'Password updated successfully.');
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'SERVER_ERROR', 500);
}