<?php

namespace App\Models;

use App\Database\Database;

class Subscription
{
    public static function getActiveForUser(int $userId): ?array
    {
        return Database::fetchOne(
            "SELECT s.*, p.name as plan_name, p.slug as plan_slug 
             FROM subscriptions s 
             JOIN plans p ON s.plan_id = p.id 
             WHERE s.user_id = ? AND s.status = 'active' 
             ORDER BY s.id DESC LIMIT 1",
            [$userId]
        );
    }

    public static function createOrUpdate(int $userId, int $planId, string $cycle = 'monthly', ?string $endsAt = null): int
    {
        Database::execute("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ?", [$userId]);

        $sql = "INSERT INTO subscriptions (user_id, plan_id, status, billing_cycle, starts_at, ends_at, created_at)
                VALUES (?, ?, 'active', ?, NOW(), ?, NOW())";

        $subId = Database::insertGetId($sql, [$userId, $planId, $cycle, $endsAt]);
        User::updatePlan($userId, $planId);
        return $subId;
    }
}
