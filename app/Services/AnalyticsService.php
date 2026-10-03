<?php

namespace App\Services;

use App\Helpers\Request;
use App\Helpers\Security;
use App\Models\QrScan;
use App\Models\QrCode;

class AnalyticsService
{
    public static function trackScan(int $qrId): void
    {
        $ip = Request::ip();
        $ipHash = Security::hashIp($ip);
        $ua = Request::userAgent();
        $referrer = Request::referrer();

        $deviceInfo = self::parseUserAgent($ua);
        $geo = self::detectGeo($ip);

        QrScan::recordScan($qrId, [
            'ip_hash'      => $ipHash,
            'country'      => $geo['country'],
            'country_code' => $geo['country_code'],
            'region'       => $geo['region'],
            'city'         => $geo['city'],
            'device_type'  => $deviceInfo['device'],
            'os'           => $deviceInfo['os'],
            'browser'      => $deviceInfo['browser'],
            'referrer'     => self::cleanReferrer($referrer),
            'user_agent'   => $ua,
        ]);

        QrCode::incrementScanCount($qrId);
    }

    public static function parseUserAgent(string $ua): array
    {
        $device = 'Desktop';
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';

        // Device Type Detection
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            $device = 'Tablet';
        } elseif (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
            $device = 'Mobile';
        }

        // OS Detection
        if (preg_match('/windows nt 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/iphone os ([0-9_]+)/i', $ua, $matches)) {
            $os = 'iOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/ipad.+os ([0-9_]+)/i', $ua, $matches)) {
            $os = 'iPadOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/mac os x ([0-9_]+)/i', $ua, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/android ([0-9\.]+)/i', $ua, $matches)) {
            $os = 'Android ' . $matches[1];
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/cros/i', $ua)) {
            $os = 'ChromeOS';
        }

        // Browser Detection
        if (preg_match('/edg\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/samsungbrowser\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Samsung Internet';
        } elseif (preg_match('/opr\/([0-9\.]+)|opera/i', $ua, $matches)) {
            $browser = 'Opera';
        } elseif (preg_match('/chrome\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = ($device === 'Mobile') ? 'Chrome Mobile' : 'Chrome';
        } elseif (preg_match('/version\/([0-9\.]+).*safari/i', $ua, $matches)) {
            $browser = ($device === 'Mobile') ? 'Mobile Safari' : 'Safari';
        } elseif (preg_match('/firefox\/([0-9\.]+)/i', $ua, $matches)) {
            $browser = 'Firefox';
        }

        return [
            'device'  => $device,
            'os'      => $os,
            'browser' => $browser,
        ];
    }

    public static function detectGeo(string $ip): array
    {
        // Check Cloudflare or Reverse Proxy country header
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cc = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
            return [
                'country'      => self::countryCodeToName($cc),
                'country_code' => $cc,
                'region'       => $_SERVER['HTTP_CF_REGION'] ?? 'Unknown',
                'city'         => $_SERVER['HTTP_CF_IPCITY'] ?? 'Unknown',
            ];
        }

        // If private/local network
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'country'      => 'Local Network',
                'country_code' => 'LO',
                'region'       => 'Local',
                'city'         => 'Localhost',
            ];
        }

        return [
            'country'      => 'United States',
            'country_code' => 'US',
            'region'       => 'Unknown',
            'city'         => 'Unknown',
        ];
    }

    private static function cleanReferrer(string $ref): string
    {
        if (empty($ref) || $ref === 'Direct') {
            return 'Direct';
        }

        $host = parse_url($ref, PHP_URL_HOST);
        return $host ?: substr($ref, 0, 100);
    }

    private static function countryCodeToName(string $code): string
    {
        $countries = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'AU' => 'Australia',
            'NL' => 'Netherlands', 'BR' => 'Brazil', 'JP' => 'Japan', 'SG' => 'Singapore',
            'ES' => 'Spain', 'IT' => 'Italy', 'MX' => 'Mexico', 'ZA' => 'South Africa',
            'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'PK' => 'Pakistan',
        ];
        return $countries[$code] ?? $code;
    }
}
