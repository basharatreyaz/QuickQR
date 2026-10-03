<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\User;
use App\Models\AuditLog;

$token = trim((string)Request::input('token', ''));

$isJson = Request::isPost() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if (empty($token)) {
    if (!$isJson) {
        header('Location: ' . Config::appUrl() . '/verify-email.html?status=missing');
        exit;
    }
    Response::validationError('Verification token is required.');
}

$user = User::findByVerificationToken($token);

if (!$user) {
    if (!$isJson) {
        header('Location: ' . Config::appUrl() . '/verify-email.html?status=invalid');
        exit;
    }
    Response::error('Invalid or expired verification token. Please request a new verification email.', 'INVALID_TOKEN', 400);
}

// Mark user as verified
User::verifyEmail($user['id']);
AuditLog::log('email_verified', $user['id'], ['email' => $user['email']]);

if (!$isJson) {
    header('Location: ' . Config::appUrl() . '/verify-email.html?status=success');
    exit;
}

Response::success([
    'verified' => true,
    'email'    => $user['email']
], 'Email address successfully verified. You can now sign in.');
