<?php

namespace App\Models;

use App\Database\Database;

class Plan
{
    public static function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM plans";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY price_monthly ASC";
        return Database::fetchAll($sql);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetchOne("SELECT * FROM plans WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetchOne("SELECT * FROM plans WHERE slug = ?", [$slug]);
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowed = [
            'name', 'description', 'price_monthly', 'price_yearly',
            'qr_limit', 'scan_limit', 'allow_dynamic', 'allow_custom_colors',
            'allow_logo', 'allow_svg', 'allow_advanced_analytics', 'allow_team',
            'allow_api', 'is_active'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE plans SET " . implode(', ', $fields) . " WHERE id = ?";
        return Database::execute($sql, $params) >= 0;
    }
}
