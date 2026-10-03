<?php

namespace App\Services;

use App\Cache\Cache;
use App\Config\Config;
use App\Helpers\Security;
use App\Helpers\Validator;
use App\Helpers\Response;
use App\Models\User;
use App\Models\QrCode;
use App\Models\AuditLog;
use App\QR\QrGenerator;
use App\QR\QrContentFormatter;

class QrService
{
    private static ?QrGenerator $generator = null;

    private static function getGenerator(): QrGenerator
    {
        if (self::$generator === null) {
            self::$generator = new QrGenerator();
        }
        return self::$generator;
    }

    public static function create(int $userId, array $data): array
    {
        // Check limits
        if (!User::canCreateQr($userId)) {
            $plan = User::getActivePlan($userId);
            Response::forbidden("Your current plan ({$plan['name']}) has reached its QR code limit of {$plan['qr_limit']}. Please upgrade to create more.");
        }

        $validator = Validator::make($data, [
            'name'        => 'required|min:1|max:150',
            'type'        => 'required|in:url,text,email,phone,sms,wifi,vcard,location,social,custom',
            'destination' => 'required',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->firstError(), $validator->errors());
        }

        $type = strtolower(trim($data['type']));
        $destination = trim($data['destination']);

        // Extra destination validation for URL/redirect types
        if ($type === 'url') {
            if (!Security::validateDestinationUrl($destination)) {
                Response::validationError('Destination must be a valid and secure URL starting with http:// or https://');
            }
        }

        $plan = User::getActivePlan($userId);

        $currentUser = User::findById($userId);
        $isAdmin = ($currentUser && ($currentUser['role'] ?? '') === 'admin');

        // Sanitize / Enforce plan restrictions on design options
        $config = $data['config'] ?? [];
        if (!is_array($config)) {
            $config = json_decode($config, true) ?: [];
        }

        if (!$isAdmin && empty($plan['allow_custom_colors'])) {
            $config['fg_color'] = '#000000';
            $config['bg_color'] = '#ffffff';
        } else {
            if (!empty($config['fg_color']) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $config['fg_color'])) {
                $config['fg_color'] = trim($config['fg_color']);
            } else {
                $config['fg_color'] = '#0f172a';
            }
            if (!empty($config['bg_color']) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $config['bg_color'])) {
                $config['bg_color'] = trim($config['bg_color']);
            } else {
                $config['bg_color'] = '#ffffff';
            }
        }

        if (!$isAdmin && empty($plan['allow_logo'])) {
            unset($config['logo_path'], $config['logo_url']);
        }

        if (empty($config['style']) || !in_array($config['style'], ['dots', 'rounded', 'square'], true)) {
            $config['style'] = 'dots';
        }

        $typeData = $data['type_data'] ?? null;

        $qrId = QrCode::create([
            'user_id'     => $userId,
            'name'        => trim($data['name']),
            'type'        => $type,
            'destination' => $destination,
            'type_data'   => $typeData,
            'config'      => $config,
            'status'      => 'active',
            'expires_at'  => !empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        AuditLog::log('qr_created', $userId, ['qr_id' => $qrId, 'name' => $data['name']]);
        Cache::delete('telemetry:public_stats');

        $qr = QrCode::findById($qrId, $userId);
        return self::formatQrPayload($qr);
    }

    public static function update(int $id, ?int $userId, array $data): array
    {
        $qr = QrCode::findById($id, $userId);
        if (!$qr) {
            Response::notFound('QR code not found.');
        }

        $ownerId = (int)$qr['user_id'];
        $actingUser = $userId ? User::findById($userId) : null;
        $isAdmin = ($actingUser && ($actingUser['role'] ?? '') === 'admin') || ($userId === null);

        $updateData = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                Response::validationError('QR Code name is required.');
            }
            $updateData['name'] = $name;
        }

        if (isset($data['destination'])) {
            $destination = trim($data['destination']);
            $type = $data['type'] ?? $qr['type'];
            if ($type === 'url' && !Security::validateDestinationUrl($destination)) {
                Response::validationError('Destination must be a valid and secure URL starting with http:// or https://');
            }
            $updateData['destination'] = $destination;
        }

        if (isset($data['type'])) {
            $updateData['type'] = strtolower(trim($data['type']));
        }

        if (isset($data['type_data'])) {
            $updateData['type_data'] = $data['type_data'];
        }

        if (isset($data['config'])) {
            $plan = User::getActivePlan($ownerId);
            $config = is_array($data['config']) ? $data['config'] : (json_decode($data['config'], true) ?: []);

            if (!$isAdmin && empty($plan['allow_custom_colors'])) {
                $config['fg_color'] = '#000000';
                $config['bg_color'] = '#ffffff';
            } else {
                if (!empty($config['fg_color']) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $config['fg_color'])) {
                    $config['fg_color'] = trim($config['fg_color']);
                } else {
                    $config['fg_color'] = '#0f172a';
                }
                if (!empty($config['bg_color']) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $config['bg_color'])) {
                    $config['bg_color'] = trim($config['bg_color']);
                } else {
                    $config['bg_color'] = '#ffffff';
                }
            }

            if (!$isAdmin && empty($plan['allow_logo'])) {
                unset($config['logo_path'], $config['logo_url']);
            }
            $updateData['config'] = $config;
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'paused'], true)) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = !empty($data['expires_at']) ? $data['expires_at'] : null;
        }

        QrCode::update($id, $userId, $updateData);
        Cache::delete("qr:short:{$qr['short_code']}");
        if (isset($updateData['status'])) {
            Cache::delete('telemetry:public_stats');
        }
        AuditLog::log('qr_updated', $userId ?? $ownerId, ['qr_id' => $id]);

        $updated = QrCode::findById($id, $userId);
        return self::formatQrPayload($updated);
    }

    public static function delete(int $id, ?int $userId = null): void
    {
        $qr = QrCode::findById($id, $userId);
        if (!$qr) {
            Response::notFound('QR code not found.');
        }

        QrCode::delete($id, $userId);
        Cache::delete("qr:short:{$qr['short_code']}");
        Cache::delete('telemetry:public_stats');
        AuditLog::log('qr_deleted', $userId ?? $qr['user_id'], ['qr_id' => $id]);
    }

    public static function duplicate(int $id, int $userId): array
    {
        if (!User::canCreateQr($userId)) {
            Response::forbidden('Your plan limit has been reached. Please upgrade to duplicate more QR codes.');
        }

        $newId = QrCode::duplicate($id, $userId);
        if (!$newId) {
            Response::notFound('QR code not found.');
        }

        AuditLog::log('qr_duplicated', $userId, ['source_qr_id' => $id, 'new_qr_id' => $newId]);
        Cache::delete('telemetry:public_stats');

        $duplicated = QrCode::findById($newId, $userId);
        return self::formatQrPayload($duplicated);
    }

    public static function toggleStatus(int $id, ?int $userId = null): array
    {
        $qr = QrCode::findById($id, $userId);
        if (!$qr) {
            Response::notFound('QR code not found.');
        }

        $newStatus = ($qr['status'] === 'active') ? 'paused' : 'active';
        QrCode::update($id, $userId, ['status' => $newStatus]);
        Cache::delete("qr:short:{$qr['short_code']}");
        Cache::delete('telemetry:public_stats');

        $qr['status'] = $newStatus;
        return self::formatQrPayload($qr);
    }

    public static function getRedirectUrl(string $shortCode): string
    {
        $appUrl = Config::appUrl();
        return "{$appUrl}/r/{$shortCode}";
    }

    public static function generatePreview(string $content, array $config = [], string $format = 'svg'): string
    {
        $generator = self::getGenerator();
        if ($format === 'png') {
            return $generator->renderPng($content, $config);
        }
        return $generator->renderSvg($content, $config);
    }

    public static function formatQrPayload(?array $qr): ?array
    {
        if (!$qr) {
            return null;
        }

        $qr['short_url'] = self::getRedirectUrl($qr['short_code'] ?? '');
        if (isset($qr['config']) && is_string($qr['config'])) {
            $qr['config'] = json_decode($qr['config'], true) ?: [];
        } elseif (!isset($qr['config']) || !is_array($qr['config'])) {
            $qr['config'] = [];
        }

        if (isset($qr['type_data']) && is_string($qr['type_data'])) {
            $qr['type_data'] = json_decode($qr['type_data'], true) ?: [];
        } elseif (!isset($qr['type_data']) || !is_array($qr['type_data'])) {
            $qr['type_data'] = [];
        }

        return $qr;
    }
}
