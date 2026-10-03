<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Models\AuditLog;

$admin = AuthMiddleware::requireAdmin();

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$userId = (int)Request::input('user_id');
if (!$userId) {
    Response::validationError('User ID is required.');
}

if ($userId === (int)$admin['id']) {
    Response::forbidden('You cannot change your own account status.');
}

$targetUser = User::findById($userId);
if (!$targetUser) {
    Response::notFound('User not found.');
}

$status = Request::input('status');
if ($status && in_array($status, ['active', 'disabled'], true)) {
    User::updateStatus($userId, $status);
    AuditLog::log('admin_updated_user_status', $admin['id'], ['target_user_id' => $userId, 'new_status' => $status]);
}

$role = Request::input('role');
if ($role && in_array($role, ['user', 'admin'], true)) {
    \App\Database\Database::execute("UPDATE users SET role = ? WHERE id = ?", [$role, $userId]);
    AuditLog::log('admin_updated_user_role', $admin['id'], ['target_user_id' => $userId, 'new_role' => $role]);
}

$planId = Request::input('plan_id');
if ($planId) {
    \App\Models\Subscription::createOrUpdate($userId, (int)$planId, 'lifetime');
    AuditLog::log('admin_updated_user_plan', $admin['id'], ['target_user_id' => $userId, 'plan_id' => $planId]);
}

$updated = User::findById($userId);
unset($updated['password_hash']);
Response::success($updated, 'User updated successfully.');