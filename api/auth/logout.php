<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

AuthMiddleware::logout();
Response::success(null, 'Logged out successfully.');