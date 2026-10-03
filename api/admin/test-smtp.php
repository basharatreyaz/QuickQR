<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Services\MailService;
use App\Models\Setting;

$admin = AuthMiddleware::requireAdmin();

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$data = Request::all();

// Use supplied test parameters or fallback to saved settings
$cfg = [
    'enabled'    => true,
    'host'       => trim((string)($data['smtp_host'] ?? Setting::get('smtp_host', ''))),
    'port'       => (int)($data['smtp_port'] ?? Setting::get('smtp_port', 587)),
    'encryption' => trim((string)($data['smtp_encryption'] ?? Setting::get('smtp_encryption', 'tls'))),
    'username'   => trim((string)($data['smtp_username'] ?? Setting::get('smtp_username', ''))),
    'password'   => (string)($data['smtp_password'] ?? Setting::get('smtp_password', '')),
    'from_email' => trim((string)($data['smtp_from_email'] ?? Setting::get('smtp_from_email', 'no-reply@quickqr.io'))),
    'from_name'  => trim((string)($data['smtp_from_name'] ?? Setting::get('smtp_from_name', 'QuickQR SaaS'))),
];

if (empty($cfg['host'])) {
    Response::validationError('SMTP Host is required to run test.');
}

// 1. Test socket handshake and authentication
$testResult = MailService::testConnection($cfg);

if (!$testResult['success']) {
    Response::error($testResult['message'], 'SMTP_TEST_FAILED', 400);
}

// 2. If test email requested, send a real test email to admin's email
$sendTestEmail = !empty($data['send_test_email']);
$recipient = trim((string)($data['test_recipient'] ?? $admin['email']));

if ($sendTestEmail && !empty($recipient)) {
    $subject = "QuickQR - SMTP Gateway Test Success";
    $time = date('Y-m-d H:i:s T');
    $body = "
        <div style='font-family:sans-serif;max-width:550px;margin:20px auto;border:1px solid #e2e8f0;border-radius:10px;padding:25px;'>
            <h2 style='color:#16a34a;margin-top:0;'><i class='fas fa-check-circle'></i> SMTP Connection Successful!</h2>
            <p>Your SMTP mail server configuration is properly set up and able to deliver emails from QuickQR.</p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;'>
                <tr><td style='padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;'>Host:</td><td style='padding:8px;border-bottom:1px solid #f1f5f9;'>{$cfg['host']}:{$cfg['port']}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;'>Encryption:</td><td style='padding:8px;border-bottom:1px solid #f1f5f9;'>{$cfg['encryption']}</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;'>From:</td><td style='padding:8px;border-bottom:1px solid #f1f5f9;'>{$cfg['from_name']} &lt;{$cfg['from_email']}&gt;</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;'>Timestamp:</td><td style='padding:8px;border-bottom:1px solid #f1f5f9;'>{$time}</td></tr>
            </table>
            <p style='color:#64748b;font-size:12px;margin-bottom:0;'>QuickQR Mail Engine v2.0</p>
        </div>
    ";

    $mailSent = MailService::send($recipient, $subject, $body, null, $cfg);
    if ($mailSent) {
        $testResult['message'] .= " A test email was also successfully sent to {$recipient}.";
    }
}

Response::success($testResult, $testResult['message']);
