<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PayPalService;

$user = AuthMiddleware::requireAuth();

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Ensure billing is enabled
if (!Setting::get('enable_billing', 0) && ($user['role'] ?? '') !== 'admin') {
    Response::forbidden('Billing is currently disabled.');
}

// Ensure PayPal is configured & enabled
if (!PayPalService::isEnabled() && ($user['role'] ?? '') !== 'admin') {
    Response::error('PayPal checkout is not currently available. Please contact administrator.', 'PAYPAL_DISABLED', 400);
}

$planId = (int)Request::input('plan_id');
$cycle = Request::input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';

$plan = Plan::findById($planId);
if (!$plan || !$plan['is_active']) {
    Response::validationError('Selected plan is invalid or unavailable.');
}

$price = (float)$plan['price_monthly'];
if ($cycle === 'yearly') {
    $price = (!empty($plan['price_yearly']) && (float)$plan['price_yearly'] > 0)
        ? (float)$plan['price_yearly']
        : round($price * 10, 2);
}

if ($price <= 0) {
    Response::error('This plan is free. No payment required.', 'FREE_PLAN', 400);
}

$currency = PayPalService::getCurrency();
$description = "{$plan['name']} Plan ({$cycle}) - " . Setting::get('app_name', 'QuickQR SaaS');
$referenceId = "user_{$user['id']}_plan_{$planId}_" . time();

try {
    $order = PayPalService::createOrder($price, $currency, $description, $referenceId);

    // Record pending transaction
    $paymentId = Payment::create([
        'user_id'        => $user['id'],
        'plan_id'        => $planId,
        'order_id'       => $order['id'],
        'payment_method' => 'paypal',
        'amount'         => $price,
        'currency'       => $currency,
        'status'         => 'created',
        'metadata'       => [
            'billing_cycle' => $cycle,
            'plan_name'     => $plan['name'],
            'order'         => $order,
        ],
    ]);

    Response::success([
        'order_id'     => $order['id'],
        'payment_id'   => $paymentId,
        'amount'       => $price,
        'currency'     => $currency,
        'plan_name'    => $plan['name'],
        'cycle'        => $cycle,
    ]);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'PAYPAL_ORDER_FAILED', 500);
}
