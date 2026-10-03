<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;

$user = AuthMiddleware::requireAuth();
$plan = User::getActivePlan($user['id']);

$isAdmin = ($user['role'] ?? '') === 'admin';
if (!$isAdmin && empty($plan['allow_logo'])) {
    Response::forbidden('Custom logo uploads require a Pro or Business plan.');
}

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    Response::validationError('Please select a valid image file to upload.');
}

$file = $_FILES['logo'];
$maxBytes = 2 * 1024 * 1024; // 2MB

if ($file['size'] > $maxBytes) {
    Response::validationError('Logo image size cannot exceed 2MB.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = [
    'image/png'     => 'png',
    'image/jpeg'    => 'jpg',
    'image/webp'    => 'webp',
    'image/svg+xml' => 'svg',
];

if (!isset($allowedMimes[$mime])) {
    Response::validationError('Only PNG, JPG, WEBP, or SVG images are supported.');
}

$ext = $allowedMimes[$mime];

// Prevent SVG script injection, XXE vectors, and event handler payloads
if ($ext === 'svg') {
    $svgRaw = file_get_contents($file['tmp_name']);
    if ($svgRaw === false) {
        Response::validationError('Unable to read uploaded SVG file.');
    }

    $forbiddenPatterns = [
        '/<!ENTITY/i',
        '/<!DOCTYPE/i',
        '/SYSTEM\s+["\']/i',
        '/<script/i',
        '/<foreignObject/i',
        '/<animate/i',
        '/javascript\s*:/i',
        '/data:text\/html/i',
        '/\bon[a-z]{3,}\s*=/i', // matches onload=, onclick=, onerror=, etc.
    ];

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $svgRaw)) {
            Response::validationError('SVG image contains prohibited scripts, XML entities, or interactive elements.');
        }
    }
}

$filename = 'logo_' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$uploadDir = dirname(__DIR__, 2) . '/storage/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    Response::error('Failed to save uploaded file.', 'UPLOAD_FAILED', 500);
}

Response::success([
    'logo_path' => $destPath,
    'filename'  => $filename,
], 'Logo uploaded successfully.');