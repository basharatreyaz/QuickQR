<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\QrService;
use App\QR\QrContentFormatter;

$data = Request::all();
$type = $data['type'] ?? 'url';
$destination = $data['destination'] ?? 'https://example.com';
$config = $data['config'] ?? [];

if (is_string($config)) {
    $config = json_decode($config, true) ?: [];
}

$format = strtolower($data['format'] ?? 'svg');

// Format content
$content = QrContentFormatter::format($type, $data['type_data'] ?? $destination);

try {
    $rendered = QrService::generatePreview($content, $config, $format);

    if ($format === 'png') {
        if (!empty($data['base64'])) {
            Response::success(['image' => 'data:image/png;base64,' . base64_encode($rendered)]);
        }
        if (!empty($data['download'])) {
            header('Content-Disposition: attachment; filename="qrcode.png"');
        }
        header('Content-Type: image/png');
        echo $rendered;
        exit;
    }

    if (!empty($data['raw'])) {
        if (!empty($data['download'])) {
            header('Content-Disposition: attachment; filename="qrcode.svg"');
        }
        header('Content-Type: image/svg+xml');
        echo $rendered;
        exit;
    }

    Response::success(['svg' => $rendered]);
} catch (\Throwable $e) {
    Response::error($e->getMessage(), 'GENERATION_ERROR', 400);
}