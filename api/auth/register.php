<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\AuthService;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

try {
    $data = Request::all();
    $user = AuthService::register($data);
    Response::success($user, 'Registration successful.', 201);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'SERVER_ERROR', 500);
}