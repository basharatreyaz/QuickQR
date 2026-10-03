<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Setting;
use App\Models\AuditLog;

$admin = AuthMiddleware::requireAdmin();

if (Request::isGet()) {
    $settings = Setting::getAll();
    Response::success($settings);
}

if (Request::isPost()) {
    $data = Request::all();
    foreach ($data as $key => $value) {
        if ($key !== 'id') {
            Setting::set($key, $value);
        }
    }
    AuditLog::log('admin_updated_settings', $admin['id']);
    Response::success(Setting::getAll(), 'Settings updated successfully.');
}

Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);