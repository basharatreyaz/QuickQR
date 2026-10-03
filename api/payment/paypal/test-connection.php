<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\PayPalService;

$admin = AuthMiddleware::requireAdmin();

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$clientId = trim((string)Request::input('client_id'));
$secret = trim((string)Request::input('secret'));
$mode = Request::input('mode', 'sandbox') === 'live' ? 'live' : 'sandbox';

if (empty($clientId) || empty($secret)) {
    Response::validationError('Both PayPal Client ID and Secret are required to test connection.');
}

$result = PayPalService::testCredentials($clientId, $secret, $mode);

if ($result['success']) {
    Response::success($result, $result['message']);
} else {
    Response::error($result['message'], 'PAYPAL_AUTH_FAILED', 400, $result);
}
