<?php

namespace App\Helpers;

class Response
{
    public static function json(mixed $payload, int $statusCode = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');

            foreach ($headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, ?string $message = null, int $statusCode = 200): void
    {
        $payload = [
            'success' => true,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        self::json($payload, $statusCode);
    }

    public static function error(string $message, string $code = 'ERROR', int $statusCode = 400, mixed $details = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        self::json([
            'success' => false,
            'error' => $error,
        ], $statusCode);
    }

    public static function validationError(string $message = 'Validation failed.', array $errors = []): void
    {
        self::error($message, 'VALIDATION_ERROR', 422, $errors);
    }

    public static function unauthorized(string $message = 'Unauthorized access.'): void
    {
        self::error($message, 'UNAUTHORIZED', 401);
    }

    public static function forbidden(string $message = 'Forbidden access.'): void
    {
        self::error($message, 'FORBIDDEN', 403);
    }

    public static function notFound(string $message = 'Resource not found.'): void
    {
        self::error($message, 'NOT_FOUND', 404);
    }

    public static function rateLimited(string $message = 'Too many requests. Please slow down.'): void
    {
        self::error($message, 'RATE_LIMITED', 429);
    }
}
