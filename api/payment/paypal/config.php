<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Services\PayPalService;
use App\Models\Setting;

$billingEnabled = (bool)(int)Setting::get('enable_billing', 0);
$paypalEnabled = (bool)(int)Setting::get('paypal_enabled', 0);
$isConfigured = PayPalService::isConfigured();

Response::success([
    'billing_enabled' => $billingEnabled,
    'paypal_enabled'  => $paypalEnabled && $isConfigured,
    'configured'      => $isConfigured,
    'mode'            => PayPalService::getMode(),
    'currency'        => PayPalService::getCurrency(),
    'client_id'       => PayPalService::getClientId(),
]);
