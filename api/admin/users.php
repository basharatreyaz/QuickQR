<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;

AuthMiddleware::requireAdmin();

$page = max(1, (int)Request::input('page', 1));
$limit = max(1, min(100, (int)Request::input('limit', 20)));
$offset = ($page - 1) * $limit;
$search = Request::input('search');

$users = User::getAll($limit, $offset, $search);
$total = User::countAll($search);

Response::success([
    'users'      => $users,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int)ceil($total / $limit),
    ]
]);