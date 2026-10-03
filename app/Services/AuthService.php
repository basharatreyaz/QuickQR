<?php

namespace App\Services;

use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimiter;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\AuditLog;
use App\Models\Setting;

class AuthService
{
    public static function register(array $data): array
    {
        RateLimiter::check('register', 5, 300);

        $validator = Validator::make($data, [
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email|max:191',
            'password' => 'required|min:6|max:100',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->firstError(), $validator->errors());
        }

        if (Setting::get('allow_registration', '1') !== '1') {
            Response::forbidden('User registration is currently disabled.');
        }

        $email = strtolower(trim($data['email']));
        if (User::findByEmail($email)) {
            Response::validationError('An account with this email already exists.');
        }

        $defaultPlanId = (int)Setting::get('default_plan_id', 1);
        $plan = Plan::findById($defaultPlanId) ?? Plan::findById(1);

        $userId = User::create([
            'name'          => trim($data['name']),
            'email'         => $email,
            'password_hash' => Security::hashPassword($data['password']),
            'role'          => 'user',
            'status'        => 'active',
            'plan_id'       => $plan ? $plan['id'] : 1,
            'api_key'       => Security::generateApiKey(),
        ]);

        if ($plan) {
            Subscription::createOrUpdate($userId, $plan['id'], 'lifetime');
        }

        AuditLog::log('user_registered', $userId, ['email' => $email]);

        $user = User::findById($userId);
        unset($user['password_hash']);

        AuthMiddleware::login($user);

        return $user;
    }

    public static function login(array $data): array
    {
        $ip = Request::ip();
        RateLimiter::check('login', 10, 60, $ip);

        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->firstError(), $validator->errors());
        }

        $email = strtolower(trim($data['email']));
        $user = User::findByEmail($email);

        // Constant-time password verification to prevent email enumeration timing attacks
        $dummyHash = '$2y$12$e80MvQ0w3Xh9yQ1uPzK9vOzB3R0d5bY7bY9rW1aM6uK8gG4nJ2hSi';
        $hashToVerify = $user ? $user['password_hash'] : $dummyHash;
        $isValidPassword = Security::verifyPassword($data['password'], $hashToVerify);

        if (!$user || !$isValidPassword) {
            AuditLog::log('login_failed', null, ['email' => $email, 'ip' => $ip]);
            Response::error('Invalid email or password.', 'INVALID_CREDENTIALS', 401);
        }

        if ($user['status'] === 'disabled') {
            Response::forbidden('Your account has been suspended. Please contact support.');
        }

        AuditLog::log('user_login', $user['id']);

        unset($user['password_hash']);
        AuthMiddleware::login($user);

        return $user;
    }

    public static function updateProfile(int $userId, array $data): array
    {
        $validator = Validator::make($data, [
            'name'  => 'required|min:2|max:100',
            'email' => 'required|email|max:191',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->firstError(), $validator->errors());
        }

        $email = strtolower(trim($data['email']));
        $existing = User::findByEmail($email);
        if ($existing && (int)$existing['id'] !== $userId) {
            Response::validationError('Email is already taken by another account.');
        }

        User::updateProfile($userId, trim($data['name']), $email);
        AuditLog::log('profile_updated', $userId);

        $user = User::findById($userId);
        unset($user['password_hash']);
        return $user;
    }

    public static function changePassword(int $userId, array $data): void
    {
        $validator = Validator::make($data, [
            'current_password' => 'required',
            'new_password'     => 'required|min:6|max:100',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->firstError(), $validator->errors());
        }

        $user = User::findById($userId);
        if (!$user || !Security::verifyPassword($data['current_password'], $user['password_hash'])) {
            Response::validationError('Current password is incorrect.');
        }

        User::updatePassword($userId, Security::hashPassword($data['new_password']));
        AuditLog::log('password_changed', $userId);
    }
}
