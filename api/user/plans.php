<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Setting;

if (Request::isGet()) {
    $plans = Plan::getAll(true);
    $user = AuthMiddleware::user();
    $activePlan = $user ? User::getActivePlan($user['id']) : null;

    Response::success([
        'plans'           => $plans,
        'active_plan'     => $activePlan,
        'billing_enabled' => (bool)(int)Setting::get('enable_billing', 0),
    ]);
}

$user = AuthMiddleware::requireAuth();

if (Request::isPost()) {
    $billingEnabled = (bool)(int)Setting::get('enable_billing', 0);
    if (!$billingEnabled && ($user['role'] ?? '') !== 'admin') {
        Response::forbidden('Subscription plans and billing are currently disabled by the administrator.');
    }

    $planId = (int)Request::input('plan_id');
    $cycle = Request::input('billing_cycle', 'monthly');

    if (!in_array($cycle, ['monthly', 'yearly'], true)) {
        $cycle = 'monthly';
    }

    $targetPlan = Plan::findById($planId);
    if (!$targetPlan || !$targetPlan['is_active']) {
        Response::validationError('Selected plan is invalid or unavailable.');
    }

    $endsAt = ($cycle === 'yearly') ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));

    Subscription::createOrUpdate($user['id'], $targetPlan['id'], $cycle, $endsAt);
    AuditLog::log('plan_upgraded', $user['id'], ['plan' => $targetPlan['name'], 'cycle' => $cycle]);

    $updatedPlan = User::getActivePlan($user['id']);
    Response::success($updatedPlan, "Successfully subscribed to the {$targetPlan['name']} plan!");
}

Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);