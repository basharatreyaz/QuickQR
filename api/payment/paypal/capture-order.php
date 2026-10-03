<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\PayPalService;

$user = AuthMiddleware::requireAuth();

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$orderId = trim((string)Request::input('order_id'));
if (empty($orderId)) {
    Response::validationError('PayPal order ID is required.');
}

try {
    $capture = PayPalService::captureOrder($orderId);

    $status = $capture['status'] ?? 'UNKNOWN';
    if ($status !== 'COMPLETED') {
        Response::error("Payment not completed. Status: {$status}", 'PAYMENT_NOT_COMPLETED', 400);
    }

    // Extract transaction details
    $captureItem = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
    $transactionId = $captureItem['id'] ?? $orderId;
    $payerEmail = $capture['payer']['email_address'] ?? null;
    $amountValue = (float)($captureItem['amount']['value'] ?? 0);

    // Retrieve or associate payment record
    $payment = Payment::findByOrderId($orderId);
    $planId = $payment ? $payment['plan_id'] : (int)Request::input('plan_id');

    $plan = Plan::findById($planId);
    if (!$plan) {
        $plan = Plan::findById(2); // Fallback to Pro
    }

    // Determine cycle and ends_at
    $meta = $payment && !empty($payment['metadata']) ? json_decode($payment['metadata'], true) : [];
    $cycle = $meta['billing_cycle'] ?? 'monthly';
    $endsAt = ($cycle === 'yearly') ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));

    if ($payment) {
        Payment::updateStatus($payment['id'], 'completed', $transactionId, $payerEmail, [
            'capture_response' => $capture,
            'completed_at'     => date('Y-m-d H:i:s'),
        ]);
    } else {
        Payment::create([
            'user_id'        => $user['id'],
            'plan_id'        => $plan['id'],
            'order_id'       => $orderId,
            'transaction_id' => $transactionId,
            'payment_method' => 'paypal',
            'amount'         => $amountValue,
            'currency'       => PayPalService::getCurrency(),
            'status'         => 'completed',
            'payer_email'    => $payerEmail,
            'metadata'       => ['capture_response' => $capture],
        ]);
    }

    // Update subscription and user's plan
    Subscription::createOrUpdate($user['id'], $plan['id'], $cycle, $endsAt);

    AuditLog::log('paypal_payment_completed', $user['id'], [
        'order_id'       => $orderId,
        'transaction_id' => $transactionId,
        'plan'           => $plan['name'],
        'amount'         => $amountValue,
        'payer_email'    => $payerEmail,
    ]);

    $updatedPlan = User::getActivePlan($user['id']);

    Response::success([
        'transaction_id' => $transactionId,
        'order_id'       => $orderId,
        'plan'           => $updatedPlan,
    ], "Payment received! Successfully upgraded to {$plan['name']} plan.");

} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'PAYPAL_CAPTURE_FAILED', 500);
}
