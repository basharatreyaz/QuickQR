<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrCode;
use App\Services\QrService;

AuthMiddleware::requireAdmin();

$page = max(1, (int)Request::input('page', 1));
$limit = max(1, min(100, (int)Request::input('limit', 25)));
$offset = ($page - 1) * $limit;
$search = Request::input('search');

$items = QrCode::listAllAdmin($limit, $offset, $search);
$formatted = array_map([QrService::class, 'formatQrPayload'], $items);

Response::success([
    'items'      => $formatted,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
    ]
]);