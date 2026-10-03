<?php

namespace App\Models;

use App\Database\Database;

class Payment
{
    public static function create(array $data): int
    {
        $sql = "INSERT INTO payments (user_id, plan_id, order_id, transaction_id, payment_method, amount, currency, status, payer_email, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        return Database::insertGetId($sql, [
            $data['user_id'],
            $data['plan_id'],
            $data['order_id'] ?? null,
            $data['transaction_id'] ?? null,
            $data['payment_method'] ?? 'paypal',
            $data['amount'] ?? 0.00,
            $data['currency'] ?? 'USD',
            $data['status'] ?? 'pending',
            $data['payer_email'] ?? null,
            isset($data['metadata']) ? (is_string($data['metadata']) ? $data['metadata'] : json_encode($data['metadata'])) : null,
        ]);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM payments WHERE id = ?", [$id]);
    }

    public static function findByOrderId(string $orderId): ?array
    {
        return Database::fetchOne("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$orderId]);
    }

    public static function updateStatus(int $id, string $status, ?string $transactionId = null, ?string $payerEmail = null, ?array $metadata = null): bool
    {
        $fields = ["status = ?"];
        $params = [$status];

        if ($transactionId !== null) {
            $fields[] = "transaction_id = ?";
            $params[] = $transactionId;
        }
        if ($payerEmail !== null) {
            $fields[] = "payer_email = ?";
            $params[] = $payerEmail;
        }
        if ($metadata !== null) {
            $fields[] = "metadata = ?";
            $params[] = json_encode($metadata);
        }

        $params[] = $id;
        $sql = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::execute($sql, $params) >= 0;
    }

    public static function listByUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, (int)$limit);
        $sql = "SELECT p.*, pl.name as plan_name
                FROM payments p
                LEFT JOIN plans pl ON p.plan_id = pl.id
                WHERE p.user_id = ?
                ORDER BY p.id DESC
                LIMIT {$limit}";

        return Database::fetchAll($sql, [$userId]);
    }

    public static function listAll(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $sql = "SELECT p.*, u.name as user_name, u.email as user_email, pl.name as plan_name
                FROM payments p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN plans pl ON p.plan_id = pl.id
                ORDER BY p.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql);
    }
}
