<?php

namespace App\Models;

use App\Database\Database;
use App\Helpers\Security;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
    }

    public static function findByApiKey(string $apiKey): ?array
    {
        return Database::fetchOne("SELECT * FROM users WHERE api_key = ? AND status = 'active'", [$apiKey]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO users (name, email, password_hash, role, status, plan_id, api_key, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        return Database::insertGetId($sql, [
            $data['name'],
            strtolower(trim($data['email'])),
            $data['password_hash'],
            $data['role'] ?? 'user',
            $data['status'] ?? 'active',
            $data['plan_id'] ?? 1,
            $data['api_key'] ?? Security::generateApiKey(),
        ]);
    }

    public static function updateProfile(int $id, string $name, string $email): bool
    {
        return Database::execute(
            "UPDATE users SET name = ?, email = ? WHERE id = ?",
            [$name, strtolower(trim($email)), $id]
        ) >= 0;
    }

    public static function updatePassword(int $id, string $passwordHash): bool
    {
        return Database::execute(
            "UPDATE users SET password_hash = ? WHERE id = ?",
            [$passwordHash, $id]
        ) > 0;
    }

    public static function updateStatus(int $id, string $status): bool
    {
        return Database::execute("UPDATE users SET status = ? WHERE id = ?", [$status, $id]) > 0;
    }

    public static function updatePlan(int $id, int $planId): bool
    {
        return Database::execute("UPDATE users SET plan_id = ? WHERE id = ?", [$planId, $id]) > 0;
    }

    public static function regenerateApiKey(int $id): string
    {
        $newKey = Security::generateApiKey();
        Database::execute("UPDATE users SET api_key = ? WHERE id = ?", [$newKey, $id]);
        return $newKey;
    }

    public static function getActivePlan(int $userId): ?array
    {
        $sql = "SELECT p.*, s.status as subscription_status, s.billing_cycle, s.ends_at
                FROM users u
                LEFT JOIN plans p ON u.plan_id = p.id
                LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = 'active'
                WHERE u.id = ?
                ORDER BY s.id DESC
                LIMIT 1";
        $plan = Database::fetchOne($sql, [$userId]);

        if (!$plan || empty($plan['id'])) {
            return Database::fetchOne("SELECT * FROM plans WHERE id = 1");
        }

        return $plan;
    }

    public static function getQrCount(int $userId): int
    {
        $row = Database::fetchOne("SELECT COUNT(*) as cnt FROM qr_codes WHERE user_id = ? AND status != 'deleted'", [$userId]);
        return (int)($row['cnt'] ?? 0);
    }

    public static function canCreateQr(int $userId): bool
    {
        $plan = self::getActivePlan($userId);
        if (!$plan) {
            return false;
        }

        $limit = (int)$plan['qr_limit'];
        if ($limit < 0 || $limit >= 999999) {
            return true;
        }

        return self::getQrCount($userId) < $limit;
    }

    public static function getAll(int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $params = [];
        $where = "WHERE 1=1";
        if ($search) {
            $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql = "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, p.name as plan_name,
                       (SELECT COUNT(*) FROM qr_codes WHERE user_id = u.id AND status != 'deleted') as qr_count,
                       (SELECT COALESCE(SUM(scan_count), 0) FROM qr_codes WHERE user_id = u.id) as total_scans
                FROM users u
                LEFT JOIN plans p ON u.plan_id = p.id
                {$where}
                ORDER BY u.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }

    public static function countAll(?string $search = null): int
    {
        $params = [];
        $where = "WHERE 1=1";
        if ($search) {
            $where .= " AND (name LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $row = Database::fetchOne("SELECT COUNT(*) as cnt FROM users {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    public static function setResetToken(int $userId, string $token, int $expiresInSeconds = 3600): bool
    {
        return Database::execute(
            "UPDATE users 
             SET reset_token = ?, 
                 reset_token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) 
             WHERE id = ?",
            [$token, $expiresInSeconds, $userId]
        ) > 0;
    }

    public static function findByResetToken(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        return Database::fetchOne(
            "SELECT * FROM users 
             WHERE reset_token = ? 
               AND reset_token_expires_at > NOW() 
               AND status = 'active'
             LIMIT 1",
            [$token]
        );
    }

    public static function clearResetToken(int $userId): bool
    {
        return Database::execute(
            "UPDATE users SET reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?",
            [$userId]
        ) >= 0;
    }

    public static function setVerificationToken(int $userId, string $token, int $expiresInSeconds = 86400): bool
    {
        return Database::execute(
            "UPDATE users 
             SET verification_token = ?, 
                 verification_token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) 
             WHERE id = ?",
            [$token, $expiresInSeconds, $userId]
        ) > 0;
    }

    public static function findByVerificationToken(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        return Database::fetchOne(
            "SELECT * FROM users 
             WHERE verification_token = ? 
               AND verification_token_expires_at > NOW() 
             LIMIT 1",
            [$token]
        );
    }

    public static function verifyEmail(int $userId): bool
    {
        return Database::execute(
            "UPDATE users 
             SET email_verified_at = NOW(), 
                 status = 'active', 
                 verification_token = NULL, 
                 verification_token_expires_at = NULL 
             WHERE id = ?",
            [$userId]
        ) > 0;
    }
}
