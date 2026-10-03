<?php

namespace App\Middleware;

use App\Config\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\User;

class AuthMiddleware
{
    private static ?array $currentUser = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            Config::load();
            $lifetime = (int)Config::get('SESSION_LIFETIME', 86400);
            $sessionName = (string)Config::get('SESSION_NAME', 'quickqr_session');

            session_name($sessionName);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function check(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        self::startSession();

        // Check Session
        if (!empty($_SESSION['user_id'])) {
            $user = User::findById((int)$_SESSION['user_id']);
            if ($user && $user['status'] === 'active') {
                self::$currentUser = $user;
                return self::$currentUser;
            }
        }

        // Check Bearer Token / API Key
        $token = Request::bearerToken();
        if (!empty($token)) {
            $user = User::findByApiKey($token);
            if ($user && $user['status'] === 'active') {
                self::$currentUser = $user;
                return self::$currentUser;
            }
        }

        return null;
    }

    public static function requireAuth(): array
    {
        self::validateOrigin();

        $user = self::check();
        if (!$user) {
            Response::unauthorized('Authentication required. Please login.');
        }
        return $user;
    }

    public static function validateOrigin(): void
    {
        if (!Request::isPost()) {
            return;
        }

        // Bearer token / API Key programmatic requests are not vulnerable to browser CSRF
        if (Request::bearerToken() !== null) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $host = $_SERVER['HTTP_HOST'] ?? null;

        if ($origin && $host) {
            $parsedOriginHost = parse_url($origin, PHP_URL_HOST);
            $parsedServerHost = parse_url('http://' . $host, PHP_URL_HOST);

            if ($parsedOriginHost && $parsedServerHost && strcasecmp($parsedOriginHost, $parsedServerHost) !== 0) {
                Response::forbidden('Cross-origin request rejected.');
            }
        }
    }

    public static function requireAdmin(): array
    {
        $user = self::requireAuth();
        if (($user['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required.');
        }
        return $user;
    }

    public static function user(): ?array
    {
        return self::check();
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        self::$currentUser = $user;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        self::$currentUser = null;
    }
}
