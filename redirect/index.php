<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Cache\Cache;
use App\Config\Config;
use App\Helpers\Request;
use App\Helpers\Security;
use App\Middleware\RateLimiter;
use App\Models\QrCode;
use App\Services\AnalyticsService;
use App\QR\QrContentFormatter;

Config::load();

// Determine short code
$shortCode = $_GET['c'] ?? null;
if (empty($shortCode)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/r/([a-zA-Z0-9_-]+)#', $uri, $matches)) {
        $shortCode = $matches[1];
    }
}

if (empty($shortCode)) {
    renderStatusPage(404, 'Invalid Link', 'No QR code destination code was provided.', 'fa-exclamation-triangle');
    exit;
}

// Rate limit
RateLimiter::check('redirect', (int)Config::get('RATE_LIMIT_REDIRECT_MAX', 120), (int)Config::get('RATE_LIMIT_REDIRECT_WINDOW', 60));

// Find QR code (Resolved from Redis/Cache in <1ms, fallback to MySQL)
$cacheKey = "qr:short:{$shortCode}";
$qr = Cache::remember($cacheKey, 86400, function () use ($shortCode) {
    return QrCode::findByShortCode($shortCode) ?: ['_not_found' => true];
});

if (isset($qr['_not_found']) || !$qr || $qr['status'] === 'deleted') {
    renderStatusPage(404, 'QR Code Not Found', 'This QR code does not exist or has been removed by its owner.', 'fa-search');
    exit;
}

// Check expiration
if (!empty($qr['expires_at']) && strtotime($qr['expires_at']) < time()) {
    renderStatusPage(410, 'QR Code Expired', 'This campaign or QR code has reached its expiration date.', 'fa-clock');
    exit;
}

if ($qr['status'] === 'paused') {
    renderStatusPage(403, 'QR Code Paused', 'This QR code is currently inactive. Please check back later.', 'fa-pause-circle');
    exit;
}

// Track analytics
try {
    AnalyticsService::trackScan((int)$qr['id']);
} catch (\Throwable $e) {
    // Prevent tracking failure from breaking redirection
    error_log("Analytics tracking error: " . $e->getMessage());
}

$type = strtolower($qr['type'] ?? 'url');
$destination = $qr['destination'];

// If URL or redirectable
if ($type === 'url' || filter_var($destination, FILTER_VALIDATE_URL)) {
    if (Security::validateDestinationUrl($destination)) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: {$destination}", true, 302);
        exit;
    }
}

// For Wi-Fi, vCard, Plain Text, Phone, Email, SMS, Location - render an elegant mobile-optimized landing view
renderActionCard($qr);
exit;

function renderStatusPage(int $statusCode, string $title, string $message, string $icon): void
{
    http_response_code($statusCode);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - Dynamic QR</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
            body { background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 1.25rem; max-width: 440px; width: 100%; padding: 2.5rem 2rem; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
            .icon-wrapper { width: 4.5rem; height: 4.5rem; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #f87171; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1.5rem; }
            h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #fff; }
            p { font-size: 0.95rem; color: #94a3b8; line-height: 1.6; margin-bottom: 2rem; }
            .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #3b82f6; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; }
            .btn:hover { background: #2563eb; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrapper">
                <i class="fas <?= htmlspecialchars($icon) ?>"></i>
            </div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <a href="<?= htmlspecialchars(Config::appUrl() ?: '/', ENT_QUOTES, 'UTF-8') ?>" class="btn"><i class="fas fa-home"></i> Return Home</a>
        </div>
    </body>
    </html>
    <?php
}

function renderActionCard(array $qr): void
{
    $type = htmlspecialchars($qr['type'] ?? 'text', ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($qr['name'] ?? 'QR Code', ENT_QUOTES, 'UTF-8');
    $rawDest = $qr['destination'] ?? '';
    $dest = htmlspecialchars($rawDest, ENT_QUOTES, 'UTF-8');
    $destJson = htmlspecialchars(json_encode($rawDest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $name ?> - QuickQR</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
            body { background: #f8fafc; color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
            .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; max-width: 480px; width: 100%; padding: 2.25rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06); text-align: center; }
            .badge { display: inline-block; padding: 0.35rem 0.85rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; }
            h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
            .content-box { background: #f1f5f9; border-radius: 0.85rem; padding: 1.25rem; margin: 1.5rem 0; text-align: left; font-size: 0.95rem; word-break: break-word; white-space: pre-wrap; color: #334155; max-height: 250px; overflow-y: auto; }
            .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: #2563eb; color: #ffffff; text-decoration: none; padding: 0.85rem 1.75rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; width: 100%; transition: background 0.2s; }
            .btn-action:hover { background: #1d4ed8; }
            .footer-tag { margin-top: 1.5rem; font-size: 0.8rem; color: #94a3b8; }
        </style>
    </head>
    <body>
        <div class="card">
            <span class="badge"><i class="fas fa-qrcode"></i> <?= $type ?></span>
            <h1><?= $name ?></h1>
            <div class="content-box"><?= $dest ?></div>
            <?php if (str_starts_with($rawDest, 'mailto:')): ?>
                <a href="<?= $dest ?>" class="btn-action"><i class="fas fa-envelope"></i> Send Email</a>
            <?php elseif (str_starts_with($rawDest, 'tel:')): ?>
                <a href="<?= $dest ?>" class="btn-action"><i class="fas fa-phone"></i> Call Number</a>
            <?php elseif (str_starts_with($rawDest, 'sms:') || str_starts_with($rawDest, 'smsto:')): ?>
                <a href="<?= $dest ?>" class="btn-action"><i class="fas fa-comment-dots"></i> Send SMS</a>
            <?php elseif (str_starts_with($rawDest, 'geo:')): ?>
                <a href="https://maps.google.com/?q=<?= urlencode(substr($rawDest, 4)) ?>" target="_blank" rel="noopener" class="btn-action"><i class="fas fa-map-marker-alt"></i> Open in Maps</a>
            <?php else: ?>
                <button id="btn-copy-action" class="btn-action">
                    <i class="fas fa-copy"></i> Copy Content
                </button>
                <script>
                    document.getElementById('btn-copy-action')?.addEventListener('click', function() {
                        const content = document.querySelector('.content-box')?.textContent || '';
                        navigator.clipboard.writeText(content).then(() => {
                            this.innerHTML = '<i class="fas fa-check"></i> Copied to Clipboard!';
                        });
                    });
                </script>
            <?php endif; ?>
            <div class="footer-tag">Powered by QuickQR Platform</div>
        </div>
    </body>
    </html>
    <?php
}
