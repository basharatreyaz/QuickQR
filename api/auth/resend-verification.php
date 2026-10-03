<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\RateLimiter;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\MailService;
use App\Config\Config;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Rate limit: 3 resend attempts per 10 minutes per IP
RateLimiter::check('resend_verification', 3, 600);

$data = Request::all();
$validator = Validator::make($data, [
    'email' => 'required|email'
]);

if ($validator->fails()) {
    Response::validationError($validator->firstError(), $validator->errors());
}

$email = strtolower(trim($data['email']));
$user = User::findByEmail($email);

$responseData = null;

if ($user && empty($user['email_verified_at'])) {
    $token = bin2hex(random_bytes(32));
    User::setVerificationToken($user['id'], $token, 86400); // 24 hours

    MailService::sendVerificationEmail($user, $token);
    AuditLog::log('verification_email_resent', $user['id'], ['email' => $email]);

    if (!Config::isProduction() || (bool)Config::get('APP_DEBUG', false)) {
        $responseData = [
            'verification_url' => Config::appUrl() . '/verify-email.html?token=' . urlencode($token),
            'token'            => $token
        ];
    }
}

Response::success(
    $responseData,
    'If an unverified account exists with this email address, a new verification link has been sent.'
);
