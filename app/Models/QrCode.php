<?php

namespace App\Models;

use App\Database\Database;
use App\Helpers\Security;

class QrCode
{
    public static function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "SELECT * FROM qr_codes WHERE id = ? AND status != 'deleted'";
        $params = [$id];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        return Database::fetchOne($sql, $params);
    }

    public static function findByShortCode(string $shortCode): ?array
    {
        return Database::fetchOne("SELECT * FROM qr_codes WHERE short_code = ?", [$shortCode]);
    }

    public static function create(array $data): int
    {
        $shortCode = $data['short_code'] ?? null;
        if (empty($shortCode)) {
            // Generate unique short code
            do {
                $shortCode = Security::generateShortCode(8);
                $existing = self::findByShortCode($shortCode);
            } while ($existing !== null);
        }

        $sql = "INSERT INTO qr_codes (user_id, name, short_code, type, destination, type_data, config, status, scan_count, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())";

        return Database::insertGetId($sql, [
            $data['user_id'],
            $data['name'],
            $shortCode,
            $data['type'] ?? 'url',
            $data['destination'],
            isset($data['type_data']) ? (is_string($data['type_data']) ? $data['type_data'] : json_encode($data['type_data'])) : null,
            isset($data['config']) ? (is_string($data['config']) ? $data['config'] : json_encode($data['config'])) : null,
            $data['status'] ?? 'active',
            $data['expires_at'] ?? null,
        ]);
    }

    public static function update(int $id, ?int $userId, array $data): bool
    {
        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (array_key_exists('destination', $data)) {
            $fields[] = "destination = ?";
            $params[] = $data['destination'];
        }
        if (array_key_exists('type', $data)) {
            $fields[] = "type = ?";
            $params[] = $data['type'];
        }
        if (array_key_exists('type_data', $data)) {
            $fields[] = "type_data = ?";
            $params[] = is_string($data['type_data']) ? $data['type_data'] : json_encode($data['type_data']);
        }
        if (array_key_exists('config', $data)) {
            $fields[] = "config = ?";
            $params[] = is_string($data['config']) ? $data['config'] : json_encode($data['config']);
        }
        if (array_key_exists('status', $data)) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        if (array_key_exists('expires_at', $data)) {
            $fields[] = "expires_at = ?";
            $params[] = $data['expires_at'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        if ($userId !== null) {
            $params[] = $userId;
            $sql = "UPDATE qr_codes SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
        } else {
            $sql = "UPDATE qr_codes SET " . implode(', ', $fields) . " WHERE id = ?";
        }
        return Database::execute($sql, $params) >= 0;
    }

    public static function delete(int $id, ?int $userId = null): bool
    {
        if ($userId !== null) {
            return Database::execute("UPDATE qr_codes SET status = 'deleted' WHERE id = ? AND user_id = ?", [$id, $userId]) > 0;
        }
        return Database::execute("UPDATE qr_codes SET status = 'deleted' WHERE id = ?", [$id]) > 0;
    }

    public static function duplicate(int $id, int $userId): ?int
    {
        $original = self::findById($id, $userId);
        if (!$original) {
            return null;
        }

        return self::create([
            'user_id'     => $userId,
            'name'        => $original['name'] . ' (Copy)',
            'type'        => $original['type'],
            'destination' => $original['destination'],
            'type_data'   => $original['type_data'],
            'config'      => $original['config'],
            'status'      => 'active',
            'expires_at'  => $original['expires_at'],
        ]);
    }

    public static function incrementScanCount(int $id): void
    {
        Database::execute("UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?", [$id]);
    }

    public static function listByUser(int $userId, array $filters = []): array
    {
        $params = [$userId];
        $where = "WHERE user_id = ? AND status != 'deleted'";

        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR destination LIKE ? OR short_code LIKE ?)";
            $term = "%{$filters['search']}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'paused', 'expired'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where .= " AND type = ?";
            $params[] = $filters['type'];
        }

        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT id, user_id, name, short_code, type, destination, type_data, config, status, scan_count, expires_at, created_at, updated_at
                FROM qr_codes
                {$where}
                ORDER BY id DESC
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }

    public static function countByUser(int $userId, array $filters = []): int
    {
        $params = [$userId];
        $where = "WHERE user_id = ? AND status != 'deleted'";

        if (!empty($filters['search'])) {
            $where .= " AND (name LIKE ? OR destination LIKE ? OR short_code LIKE ?)";
            $term = "%{$filters['search']}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'paused', 'expired'])) {
            $where .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where .= " AND type = ?";
            $params[] = $filters['type'];
        }

        $row = Database::fetchOne("SELECT COUNT(*) as cnt FROM qr_codes {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    public static function listAllAdmin(int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $params = [];
        $where = "WHERE q.status != 'deleted'";

        if ($search) {
            $where .= " AND (q.name LIKE ? OR q.short_code LIKE ? OR u.email LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "SELECT q.*, u.name as user_name, u.email as user_email
                FROM qr_codes q
                JOIN users u ON q.user_id = u.id
                {$where}
                ORDER BY q.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }
}
