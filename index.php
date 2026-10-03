<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Helpers\ErrorHandler;

// Register production error and exception handlers
ErrorHandler::register();

// Apply global baseline security headers for all responses
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// Strip /sass and /public prefixes if present
$cleanPath = preg_replace('#^/sass#i', '', $path);
$cleanPath = preg_replace('#^/public#i', '', $cleanPath);
$cleanPath = trim($cleanPath, '/');

// Security check: Block attempts to access internal directories or dotfiles
if (
    str_starts_with($cleanPath, '.') ||
    preg_match('#^(app|config|database|storage|tests|vendor)(\/|$)#i', $cleanPath)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden: Access to this resource is denied.';
    exit;
}

// 1. QR Code Dynamic Redirects: r/{code}
if (preg_match('#^r/([a-zA-Z0-9_-]+)#', $cleanPath, $matches)) {
    $_GET['c'] = $matches[1];
    require __DIR__ . '/redirect/index.php';
    exit;
}

// 2. Root / Landing page
if ($cleanPath === '' || $cleanPath === 'index' || $cleanPath === 'index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/index.html');
    exit;
}

// 3. Clean HTML routes without .html (e.g. /login, /dashboard, /pricing, /admin)
if (file_exists(__DIR__ . '/public/' . $cleanPath . '.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/' . $cleanPath . '.html');
    exit;
}

// 4. Exact files in public (e.g. login.html, css/app.css, js/api.js, favicon.ico)
$publicFile = __DIR__ . '/public/' . $cleanPath;
if (file_exists($publicFile) && is_file($publicFile)) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        'xml'  => 'application/xml',
        'txt'  => 'text/plain',
        'html' => 'text/html; charset=utf-8',
    ];

    if (isset($mimes[$ext])) {
        header("Content-Type: {$mimes[$ext]}");
    }

    // Static asset caching headers (1 day for CSS/JS/images)
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'ico', 'webp'], true)) {
        header('Cache-Control: public, max-age=86400');
    }

    readfile($publicFile);
    exit;
}

// 5. Whitelisted direct PHP entrypoints (api/* and redirect/* only)
if (preg_match('#^(api|redirect)/#i', $cleanPath)) {
    $phpFile = __DIR__ . '/' . $cleanPath;
    if (file_exists($phpFile) && is_file($phpFile) && pathinfo($phpFile, PATHINFO_EXTENSION) === 'php') {
        require $phpFile;
        exit;
    }
}

// 6. 404 Fallback
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
if (file_exists(__DIR__ . '/public/404.html')) {
    readfile(__DIR__ . '/public/404.html');
} else {
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested page could not be found.</p><a href='/'>Go Home</a></body></html>";
}
exit;
