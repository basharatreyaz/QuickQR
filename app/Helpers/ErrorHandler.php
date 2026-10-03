<?php

namespace App\Helpers;

use App\Config\Config;
use Throwable;

class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        Config::load();
        $isProd = Config::isProduction();
        $debug = (bool)Config::get('APP_DEBUG', true);

        // Configure error reporting
        error_reporting(E_ALL);

        if ($isProd || !$debug) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');
            
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (is_dir($logDir) && is_writable($logDir)) {
                ini_set('error_log', $logDir . '/error.log');
            }

            set_exception_handler([self::class, 'handleException']);
            set_error_handler([self::class, 'handleError']);
            register_shutdown_function([self::class, 'handleShutdown']);
        } else {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        }

        self::$registered = true;
    }

    public static function handleException(Throwable $e): void
    {
        self::logThrowable($e);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::isApiRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => 'An unexpected server error occurred. Please try again later.'
                ]
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $errorPage = dirname(__DIR__, 2) . '/public/500.html';
        if (file_exists($errorPage)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($errorPage);
            exit;
        }

        echo "<!DOCTYPE html><html><head><title>500 Server Error</title></head><body><h1>500 Internal Server Error</h1><p>An unexpected error occurred.</p></body></html>";
        exit;
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::logMessage("PHP Error [{$severity}]: {$message} in {$file} on line {$line}");
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::logMessage("Fatal Error [{$error['type']}]: {$error['message']} in {$error['file']} on line {$error['line']}");

            if (!headers_sent()) {
                http_response_code(500);
            }

            if (self::isApiRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INTERNAL_SERVER_ERROR',
                        'message' => 'An unexpected fatal error occurred.'
                    ]
                ], JSON_UNESCAPED_SLASHES);
            } else {
                $errorPage = dirname(__DIR__, 2) . '/public/500.html';
                if (file_exists($errorPage)) {
                    header('Content-Type: text/html; charset=utf-8');
                    readfile($errorPage);
                }
            }
        }
    }

    private static function logThrowable(Throwable $e): void
    {
        $time = date('Y-m-d H:i:s');
        $msg = sprintf(
            "[%s] Exception: %s in %s:%d\nStack trace:\n%s\n",
            $time,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        self::logMessage($msg);
    }

    private static function logMessage(string $msg): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/error.log';
        @file_put_contents($logFile, $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($uri, '/api/') || str_contains($accept, 'application/json');
    }
}
