<?php

namespace App\Services;

use App\Config\Config;
use App\Models\Setting;
use Throwable;

class MailService
{
    public static function isSmtpEnabled(): bool
    {
        return (bool)(int)Setting::get('smtp_enabled', 0);
    }

    public static function isEmailVerificationRequired(): bool
    {
        return (bool)(int)Setting::get('enable_email_verification', 0);
    }

    public static function getSmtpConfig(): array
    {
        return [
            'enabled'    => (bool)(int)Setting::get('smtp_enabled', 0),
            'host'       => (string)Setting::get('smtp_host', ''),
            'port'       => (int)Setting::get('smtp_port', 587),
            'encryption' => (string)Setting::get('smtp_encryption', 'tls'), // tls, ssl, none
            'username'   => (string)Setting::get('smtp_username', ''),
            'password'   => (string)Setting::get('smtp_password', ''),
            'from_email' => (string)Setting::get('smtp_from_email', 'no-reply@quickqr.io'),
            'from_name'  => (string)Setting::get('smtp_from_name', Setting::get('app_name', 'QuickQR SaaS')),
        ];
    }

    /**
     * Dispatch an HTML email via configured SMTP socket or fallback
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $altBody = null, ?array $overrideConfig = null): bool
    {
        $cfg = $overrideConfig ?? self::getSmtpConfig();

        // If SMTP is enabled with a configured host, send via SMTP socket
        if (!empty($cfg['enabled']) && !empty($cfg['host'])) {
            try {
                return self::sendViaSmtp($to, $subject, $htmlBody, $altBody, $cfg);
            } catch (Throwable $e) {
                error_log("MailService SMTP Send Error: " . $e->getMessage());
                // Fall through to logging/mail fallback
            }
        }

        // Fallback: Use PHP mail() function if available
        $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : 'no-reply@quickqr.io';
        $fromName = !empty($cfg['from_name']) ? $cfg['from_name'] : 'QuickQR SaaS';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromName), $fromEmail),
            'Reply-To: ' . $fromEmail,
            'X-Mailer: QuickQR-Mailer/2.0'
        ];

        $sent = false;
        if (function_exists('mail') && ini_get('sendmail_path')) {
            $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
        }

        // Always log outgoing email in storage/logs/mail.log for audit and development
        self::logEmail($to, $subject, $htmlBody);

        return $sent || true;
    }

    /**
     * Send email verification link to user
     */
    public static function sendVerificationEmail(array $user, string $token): bool
    {
        $appName = Setting::get('app_name', 'QuickQR SaaS');
        $appUrl = Config::appUrl();
        $verifyUrl = $appUrl . '/verify-email.html?token=' . urlencode($token);

        $subject = "Verify your email address - {$appName}";

        $html = self::renderEmailTemplate(
            title: "Confirm Your Email Address",
            previewText: "Please verify your email address to activate your {$appName} account.",
            content: "
                <p style='color:#334155;font-size:15px;line-height:1.6;'>Hello <strong>" . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                <p style='color:#334155;font-size:15px;line-height:1.6;'>Thank you for creating an account with <strong>{$appName}</strong>. To get started creating dynamic QR codes and tracking your campaigns, please confirm your email address by clicking the button below:</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='{$verifyUrl}' style='display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:15px;'>Verify Email Address</a>
                </div>
                <p style='color:#64748b;font-size:13px;line-height:1.5;'>This verification link will expire in 24 hours. If you did not create an account, no further action is required.</p>
                <div style='border-top:1px solid #e2e8f0;margin-top:24px;padding-top:16px;'>
                    <p style='color:#94a3b8;font-size:12px;margin:0;'>If button doesn't work, copy and paste this URL into your browser:<br><a href='{$verifyUrl}' style='color:#4f46e5;word-break:break-all;'>{$verifyUrl}</a></p>
                </div>
            "
        );

        return self::send($user['email'], $subject, $html);
    }

    /**
     * Send password reset link to user
     */
    public static function sendPasswordResetEmail(array $user, string $token): bool
    {
        $appName = Setting::get('app_name', 'QuickQR SaaS');
        $appUrl = Config::appUrl();
        $resetUrl = $appUrl . '/reset-password.html?token=' . urlencode($token) . '&email=' . urlencode($user['email']);

        $subject = "Password Reset Request - {$appName}";

        $html = self::renderEmailTemplate(
            title: "Reset Your Account Password",
            previewText: "Instructions to reset your {$appName} account password.",
            content: "
                <p style='color:#334155;font-size:15px;line-height:1.6;'>Hello <strong>" . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                <p style='color:#334155;font-size:15px;line-height:1.6;'>We received a request to reset the password for your <strong>{$appName}</strong> account. Click the button below to choose a new password:</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='{$resetUrl}' style='display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:15px;'>Set New Password</a>
                </div>
                <p style='color:#64748b;font-size:13px;line-height:1.5;'>This link is valid for 1 hour. If you didn't request a password reset, you can safely ignore this email.</p>
                <div style='border-top:1px solid #e2e8f0;margin-top:24px;padding-top:16px;'>
                    <p style='color:#94a3b8;font-size:12px;margin:0;'>If button doesn't work, open this link directly:<br><a href='{$resetUrl}' style='color:#4f46e5;word-break:break-all;'>{$resetUrl}</a></p>
                </div>
            "
        );

        return self::send($user['email'], $subject, $html);
    }

    /**
     * Test SMTP socket connection and credentials
     */
    public static function testConnection(?array $cfg = null): array
    {
        $config = $cfg ?? self::getSmtpConfig();

        if (empty($config['host'])) {
            return ['success' => false, 'message' => 'SMTP Host cannot be blank.'];
        }

        try {
            $host = $config['host'];
            $port = (int)($config['port'] ?: 587);
            $enc = strtolower((string)($config['encryption'] ?? 'tls'));

            $prefix = ($enc === 'ssl') ? 'ssl://' : 'tcp://';
            $timeout = 10;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client(
                "{$prefix}{$host}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return ['success' => false, 'message' => "Socket connection failed ({$errno}): {$errstr}"];
            }

            stream_set_timeout($socket, $timeout);

            // 1. Read greeting
            $res = self::readSocketResponse($socket);
            if (!str_starts_with($res, '220')) {
                fclose($socket);
                return ['success' => false, 'message' => "Unexpected greeting response: {$res}"];
            }

            // 2. EHLO
            $clientName = gethostname() ?: 'localhost';
            self::writeSocket($socket, "EHLO {$clientName}\r\n");
            $res = self::readSocketResponse($socket);

            // 3. STARTTLS if required
            if ($enc === 'tls') {
                self::writeSocket($socket, "STARTTLS\r\n");
                $res = self::readSocketResponse($socket);
                if (!str_starts_with($res, '220')) {
                    fclose($socket);
                    return ['success' => false, 'message' => "STARTTLS failed: {$res}"];
                }

                $cryptoSuccess = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$cryptoSuccess) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'Failed to establish TLS encryption handshake.'];
                }

                // Resend EHLO over TLS
                self::writeSocket($socket, "EHLO {$clientName}\r\n");
                $res = self::readSocketResponse($socket);
            }

            // 4. AUTH LOGIN if credentials supplied
            if (!empty($config['username'])) {
                self::writeSocket($socket, "AUTH LOGIN\r\n");
                $res = self::readSocketResponse($socket);
                if (!str_starts_with($res, '334')) {
                    fclose($socket);
                    return ['success' => false, 'message' => "AUTH LOGIN rejected: {$res}"];
                }

                self::writeSocket($socket, base64_encode($config['username']) . "\r\n");
                $res = self::readSocketResponse($socket);
                if (!str_starts_with($res, '334')) {
                    fclose($socket);
                    return ['success' => false, 'message' => "Username rejected: {$res}"];
                }

                self::writeSocket($socket, base64_encode($config['password'] ?? '') . "\r\n");
                $res = self::readSocketResponse($socket);
                if (!str_starts_with($res, '235')) {
                    fclose($socket);
                    return ['success' => false, 'message' => "Authentication failed: {$res}"];
                }
            }

            // 5. QUIT
            self::writeSocket($socket, "QUIT\r\n");
            fclose($socket);

            return [
                'success' => true,
                'message' => "Successfully connected and authenticated with SMTP server ({$host}:{$port})."
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send email via direct SMTP socket
     */
    private static function sendViaSmtp(string $to, string $subject, string $htmlBody, ?string $altBody, array $cfg): bool
    {
        $host = $cfg['host'];
        $port = (int)($cfg['port'] ?: 587);
        $enc  = strtolower((string)($cfg['encryption'] ?? 'tls'));

        $prefix = ($enc === 'ssl') ? 'ssl://' : 'tcp://';
        $timeout = 15;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new \Exception("Could not connect to SMTP server: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        self::readSocketResponse($socket);

        $clientName = gethostname() ?: 'localhost';
        self::writeSocket($socket, "EHLO {$clientName}\r\n");
        self::readSocketResponse($socket);

        if ($enc === 'tls') {
            self::writeSocket($socket, "STARTTLS\r\n");
            $res = self::readSocketResponse($socket);
            if (!str_starts_with($res, '220')) {
                fclose($socket);
                throw new \Exception("STARTTLS negotiation failed: {$res}");
            }

            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new \Exception("Failed to establish TLS encryption handshake.");
            }

            self::writeSocket($socket, "EHLO {$clientName}\r\n");
            self::readSocketResponse($socket);
        }

        if (!empty($cfg['username'])) {
            self::writeSocket($socket, "AUTH LOGIN\r\n");
            self::readSocketResponse($socket);
            self::writeSocket($socket, base64_encode($cfg['username']) . "\r\n");
            self::readSocketResponse($socket);
            self::writeSocket($socket, base64_encode($cfg['password'] ?? '') . "\r\n");
            $authRes = self::readSocketResponse($socket);
            if (!str_starts_with($authRes, '235')) {
                fclose($socket);
                throw new \Exception("SMTP Authentication failed: {$authRes}");
            }
        }

        $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : 'no-reply@quickqr.io';
        $fromName  = !empty($cfg['from_name']) ? $cfg['from_name'] : 'QuickQR SaaS';

        self::writeSocket($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        self::readSocketResponse($socket);

        self::writeSocket($socket, "RCPT TO: <{$to}>\r\n");
        self::readSocketResponse($socket);

        self::writeSocket($socket, "DATA\r\n");
        self::readSocketResponse($socket);

        $date = date('r');
        $msgId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . ($clientName ?: 'quickqr') . '>';

        $headers = [
            "Date: {$date}",
            "Message-ID: {$msgId}",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: <{$to}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: QuickQR-Platform-Engine/2.0"
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.\r\n";
        self::writeSocket($socket, $payload);
        $dataRes = self::readSocketResponse($socket);

        self::writeSocket($socket, "QUIT\r\n");
        fclose($socket);

        self::logEmail($to, $subject, $htmlBody);

        return str_starts_with($dataRes, '250');
    }

    private static function readSocketResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    private static function writeSocket($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    private static function logEmail(string $to, string $subject, string $body): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            $logEntry = sprintf(
                "[%s] OUTGOING EMAIL\nTo: %s\nSubject: %s\nTime: %s\nContent Preview: %s\n%s\n",
                date('Y-m-d H:i:s'),
                $to,
                $subject,
                date('c'),
                strip_tags(mb_substr($body, 0, 300)),
                str_repeat('-', 60)
            );
            @file_put_contents($logDir . '/mail.log', $logEntry, FILE_APPEND | LOCK_EX);
        }
    }

    private static function renderEmailTemplate(string $title, string $previewText, string $content): string
    {
        $appName = htmlspecialchars(Setting::get('app_name', 'QuickQR SaaS'), ENT_QUOTES, 'UTF-8');
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
                .email-wrap { max-width: 580px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
                .header { background: #0f172a; padding: 24px 30px; text-align: center; }
                .body { padding: 32px 30px; }
                .footer { background: #f8fafc; padding: 20px 30px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
            </style>
        </head>
        <body>
            <span style='display:none;font-size:0px;line-height:0px;max-height:0px;opacity:0;overflow:hidden;'>{$previewText}</span>
            <div class='email-wrap'>
                <div class='header'>
                    <div style='font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;'>
                        <span style='display:inline-block;width:24px;height:24px;background:#4f46e5;border-radius:6px;line-height:24px;text-align:center;font-size:12px;margin-right:8px;'>QR</span>
                        {$appName}
                    </div>
                </div>
                <div class='body'>
                    <h2 style='font-size:20px;font-weight:700;color:#0f172a;margin-top:0;margin-bottom:16px;'>{$title}</h2>
                    {$content}
                </div>
                <div class='footer'>
                    <p style='margin:0 0 6px 0;'>&copy; " . date('Y') . " {$appName}. All rights reserved.</p>
                    <p style='margin:0;'>Next-generation dynamic QR infrastructure with privacy-preserving telemetry.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
