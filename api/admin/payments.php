<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Payment;

$admin = AuthMiddleware::requireAdmin();

$limit = max(1, min(100, (int)Request::input('limit', 50)));
$offset = max(0, (int)Request::input('offset', 0));

$payments = Payment::listAll($limit, $offset);

Response::success([
    'payments' => $payments,
    'total'    => count($payments),
]);
