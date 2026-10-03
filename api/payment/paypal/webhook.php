<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\AuditLog;

$data = Request::getJson();
$eventType = $data['event_type'] ?? '';

if (empty($eventType)) {
    Response::json(['status' => 'ignored'], 200);
}

AuditLog::log('paypal_webhook_received', null, ['event_type' => $eventType]);

switch ($eventType) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        $resource = $data['resource'] ?? [];
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        $captureId = $resource['id'] ?? null;
        if ($orderId) {
            $payment = Payment::findByOrderId($orderId);
            if ($payment && $payment['status'] !== 'completed') {
                Payment::updateStatus($payment['id'], 'completed', $captureId, $resource['payer']['email_address'] ?? null, $data);
            }
        }
        break;

    case 'BILLING.SUBSCRIPTION.CANCELLED':
    case 'BILLING.SUBSCRIPTION.EXPIRED':
        // Handle subscription expiration or cancellation if recurring agreement used
        break;
}

Response::json(['status' => 'processed'], 200);
