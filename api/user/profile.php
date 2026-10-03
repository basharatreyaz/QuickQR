<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\AuthService;

$user = AuthMiddleware::requireAuth();

if (Request::isGet()) {
    unset($user['password_hash']);
    $user['plan'] = User::getActivePlan($user['id']);
    $user['qr_count'] = User::getQrCount($user['id']);
    Response::success($user);
}

if (Request::isPost()) {
    $action = Request::input('action');
    if ($action === 'regenerate_api_key') {
        $newKey = User::regenerateApiKey($user['id']);
        Response::success(['api_key' => $newKey], 'API key regenerated successfully.');
    }

    $updated = AuthService::updateProfile($user['id'], Request::all());
    Response::success($updated, 'Profile updated successfully.');
}

Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);