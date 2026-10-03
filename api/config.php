<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Models\Setting;

$settings = Setting::getAll();

Response::success([
    'app_name'           => $settings['app_name'] ?? 'QuickQR SaaS',
    'app_url'            => $settings['app_url'] ?? '',
    'allow_registration' => (bool)(int)($settings['allow_registration'] ?? 1),
    'billing_enabled'    => (bool)(int)($settings['enable_billing'] ?? 0),
]);
