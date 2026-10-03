<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\QrCode;
use App\Services\QrService;

$user = AuthMiddleware::requireAuth();

$page = max(1, (int)Request::input('page', 1));
$limit = max(1, min(100, (int)Request::input('limit', 15)));
$offset = ($page - 1) * $limit;

$filters = [
    'search' => Request::input('search'),
    'status' => Request::input('status'),
    'type'   => Request::input('type'),
    'limit'  => $limit,
    'offset' => $offset,
];

$items = QrCode::listByUser($user['id'], $filters);
$total = QrCode::countByUser($user['id'], $filters);

$formattedItems = array_map([QrService::class, 'formatQrPayload'], $items);

Response::success([
    'items'      => $formattedItems,
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'total_pages'=> (int)ceil($total / $limit),
    ]
]);