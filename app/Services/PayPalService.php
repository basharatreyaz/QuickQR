<?php

namespace App\Services;

use App\Models\Setting;
use Exception;

class PayPalService
{
    public static function isEnabled(): bool
    {
        return (bool)(int)Setting::get('paypal_enabled', 0) && self::isConfigured();
    }

    public static function isConfigured(): bool
    {
        $clientId = trim((string)Setting::get('paypal_client_id', ''));
        $secret = trim((string)Setting::get('paypal_secret', ''));
        return !empty($clientId) && !empty($secret);
    }

    public static function getMode(): string
    {
        return Setting::get('paypal_mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public static function getCurrency(): string
    {
        return strtoupper((string)Setting::get('paypal_currency', 'USD'));
    }

    public static function getClientId(): string
    {
        return trim((string)Setting::get('paypal_client_id', ''));
    }

    public static function getSecret(): string
    {
        return trim((string)Setting::get('paypal_secret', ''));
    }

    public static function getBaseUrl(?string $mode = null): string
    {
        $mode = $mode ?: self::getMode();
        return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get OAuth Access Token from PayPal
     */
    public static function getAccessToken(?string $clientId = null, ?string $secret = null, ?string $mode = null): string
    {
        $clientId = trim((string)($clientId ?: self::getClientId()), "\"' \t\n\r\0\x0B");
        $secret = trim((string)($secret ?: self::getSecret()), "\"' \t\n\r\0\x0B");
        $baseUrl = self::getBaseUrl($mode);

        if (empty($clientId) || empty($secret)) {
            throw new Exception("PayPal Client ID or Secret is missing in admin configuration.");
        }

        $ch = curl_init("{$baseUrl}/v1/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => "{$clientId}:{$secret}",
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false, // local dev compatibility
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error connecting to PayPal: {$err}");
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $errorMsg = $data['error_description'] ?? $data['message'] ?? 'Failed to authenticate with PayPal. Check credentials.';
            throw new Exception("PayPal Auth Error (HTTP {$httpCode}): {$errorMsg}");
        }

        return $data['access_token'];
    }

    /**
     * Test PayPal Credentials with Auto Mode Detection
     */
    public static function testCredentials(string $clientId, string $secret, string $mode = 'sandbox'): array
    {
        $clientId = trim($clientId, "\"' \t\n\r\0\x0B");
        $secret = trim($secret, "\"' \t\n\r\0\x0B");

        try {
            $token = self::getAccessToken($clientId, $secret, $mode);
            return [
                'success' => true,
                'message' => 'PayPal credentials verified successfully! Connection established in ' . strtoupper($mode) . ' mode.',
            ];
        } catch (Exception $e) {
            // Check if credentials match the alternate mode (e.g., Live instead of Sandbox)
            $altMode = ($mode === 'sandbox') ? 'live' : 'sandbox';
            try {
                self::getAccessToken($clientId, $secret, $altMode);
                return [
                    'success'        => false,
                    'message'        => "Authentication failed in " . strtoupper($mode) . " mode. However, your credentials WORK in " . strtoupper($altMode) . " mode! Please switch the Environment setting to '" . ucfirst($altMode) . "'.",
                    'suggested_mode' => $altMode,
                ];
            } catch (Exception $ignored) {
                // Fails on both
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create PayPal Order (v2/checkout/orders)
     */
    public static function createOrder(float $amount, string $currency, string $description, string $referenceId): array
    {
        $accessToken = self::getAccessToken();
        $baseUrl = self::getBaseUrl();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $referenceId,
                    'description'  => substr($description, 0, 127),
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name'          => (string)Setting::get('app_name', 'QuickQR SaaS'),
                'landing_page'        => 'NO_PREFERENCE',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $ch = curl_init("{$baseUrl}/v2/checkout/orders");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$accessToken}",
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error creating PayPal order: {$err}");
        }

        $data = json_decode($response, true);
        if ($httpCode !== 201 && $httpCode !== 200) {
            $errorMsg = $data['message'] ?? $data['details'][0]['description'] ?? 'Unable to create PayPal order.';
            throw new Exception("PayPal Order Creation Error (HTTP {$httpCode}): {$errorMsg}");
        }

        return $data;
    }

    /**
     * Capture PayPal Order (v2/checkout/orders/{order_id}/capture)
     */
    public static function captureOrder(string $orderId): array
    {
        $accessToken = self::getAccessToken();
        $baseUrl = self::getBaseUrl();

        $ch = curl_init("{$baseUrl}/v2/checkout/orders/{$orderId}/capture");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$accessToken}",
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error capturing PayPal order: {$err}");
        }

        $data = json_decode($response, true);
        if ($httpCode !== 201 && $httpCode !== 200) {
            $errorMsg = $data['message'] ?? $data['details'][0]['description'] ?? 'Unable to capture PayPal payment.';
            throw new Exception("PayPal Capture Error (HTTP {$httpCode}): {$errorMsg}");
        }

        return $data;
    }
}
