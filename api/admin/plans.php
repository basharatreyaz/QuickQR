<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Plan;
use App\Models\AuditLog;

$admin = AuthMiddleware::requireAdmin();

if (Request::isGet()) {
    $plans = Plan::getAll(false);
    Response::success($plans);
}

if (Request::isPost()) {
    $id = (int)Request::input('id');
    if (!$id) {
        Response::validationError('Plan ID is required.');
    }

    $target = Plan::findById($id);
    if (!$target) {
        Response::notFound('Plan not found.');
    }

    $data = Request::all();
    Plan::update($id, $data);
    AuditLog::log('admin_updated_plan', $admin['id'], ['plan_id' => $id]);

    $updated = Plan::findById($id);
    Response::success($updated, 'Plan updated successfully.');
}

Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);