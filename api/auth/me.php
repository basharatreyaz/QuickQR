<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Models\Setting;

$user = AuthMiddleware::requireAuth();
unset($user['password_hash']);

$plan = User::getActivePlan($user['id']);
$qrCount = User::getQrCount($user['id']);

$user['plan'] = $plan;
$user['qr_count'] = $qrCount;
$user['can_create_qr'] = User::canCreateQr($user['id']);
$user['billing_enabled'] = (bool)(int)Setting::get('enable_billing', 0);

Response::success($user);