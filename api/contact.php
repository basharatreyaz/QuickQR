<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Middleware\RateLimiter;
use App\Models\AuditLog;
use App\Config\Config;

if (!Request::isPost()) {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Rate limit: 5 inquiries per 10 minutes per IP
RateLimiter::check('contact_form', 5, 600);

$data = Request::all();

$validator = Validator::make($data, [
    'name'    => 'required|min:2|max:100',
    'email'   => 'required|email|max:150',
    'subject' => 'required|min:3|max:200',
    'message' => 'required|min:5|max:5000'
]);

if ($validator->fails()) {
    Response::validationError($validator->firstError(), $validator->errors());
}

$name = trim($data['name']);
$email = strtolower(trim($data['email']));
$subject = trim($data['subject']);
$message = trim($data['message']);

// Log inquiry to audit log
AuditLog::log('contact_inquiry_received', null, [
    'name'    => $name,
    'email'   => $email,
    'subject' => $subject,
    'preview' => mb_substr($message, 0, 200)
]);

// Write detailed inquiry to contact inquiries log
$logEntry = sprintf(
    "[%s] IP: %s | From: %s <%s> | Subject: %s\nMessage:\n%s\n%s\n",
    date('Y-m-d H:i:s'),
    Request::ip(),
    $name,
    $email,
    $subject,
    $message,
    str_repeat('-', 60)
);

$logDir = dirname(__DIR__) . '/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    @file_put_contents($logDir . '/contact_inquiries.log', $logEntry, FILE_APPEND | LOCK_EX);
}

Response::success(
    null,
    "Thank you, {$name}! Your message has been received and our team will get back to you shortly."
);
