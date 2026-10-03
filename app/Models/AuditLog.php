<?php

namespace App\Models;

use App\Database\Database;
use App\Helpers\Request;

class AuditLog
{
    public static function log(string $action, ?int $userId = null, mixed $details = null): void
    {
        $detailsStr = is_array($details) ? json_encode($details) : (string)$details;
        $ip = Request::ip();

        Database::execute(
            "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $action, $detailsStr, $ip]
        );
    }

    public static function getRecent(int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT a.*, u.email as user_email, u.name as user_name
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.id DESC
             LIMIT ?",
            [$limit]
        );
    }
}
