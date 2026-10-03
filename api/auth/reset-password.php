<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\Security;
use App\Models\User;
use App\Models\AuditLog;
use App\Middleware\RateLimiter;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

RateLimiter::check('reset_password', 10, 300);

$data = Request::all();

// Mode 1: Confirm Password Reset (requires valid token + new_password)
if (!empty($data['token']) || !empty($data['new_password'])) {
    $validator = Validator::make($data, [
        'token'        => 'required|min:16|max:128',
        'new_password' => 'required|min:6|max:100'
    ]);

    if ($validator->fails()) {
        Response::validationError($validator->firstError(), $validator->errors());
    }

    $token = trim((string)$data['token']);
    $user = User::findByResetToken($token);

    if (!$user) {
        Response::error('Invalid or expired password reset link. Please request a new one.', 'INVALID_TOKEN', 400);
    }

    // Optional email check if supplied
    if (!empty($data['email']) && strtolower(trim($data['email'])) !== strtolower(trim($user['email']))) {
        Response::error('Email does not match the reset token.', 'INVALID_TOKEN', 400);
    }

    // Update password
    User::updatePassword($user['id'], Security::hashPassword($data['new_password']));

    // Invalidate reset token immediately (single-use)
    User::clearResetToken($user['id']);

    AuditLog::log('password_reset_completed', $user['id'], ['email' => $user['email']]);

    Response::success(null, 'Your password has been successfully reset. You can now sign in with your new password.');
}

// Mode 2: Request Reset Instructions (email only)
$validator = Validator::make($data, [
    'email' => 'required|email'
]);

if ($validator->fails()) {
    Response::validationError($validator->firstError(), $validator->errors());
}

$email = strtolower(trim($data['email']));
$user = User::findByEmail($email);

$responseData = null;

if ($user && $user['status'] === 'active') {
    // Generate secure 64-character token
    $token = bin2hex(random_bytes(32));
    User::setResetToken($user['id'], $token, 3600); // 1-hour expiry

    AuditLog::log('password_reset_requested', $user['id'], ['email' => $email]);

    $appUrl = Config::appUrl();
    $resetUrl = $appUrl . '/reset-password.html?token=' . urlencode($token) . '&email=' . urlencode($email);

    // Provide reset URL in dev/debug mode for convenient testing without mail daemon
    if (!Config::isProduction() || (bool)Config::get('APP_DEBUG', false)) {
        $responseData = [
            'reset_url' => $resetUrl,
            'token'     => $token
        ];
    }
}

Response::success(
    $responseData,
    'If that email exists in our records, password reset instructions have been sent.'
);