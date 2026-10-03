<?php

namespace App\Models;

use App\Database\Database;

class QrScan
{
    public static function recordScan(int $qrCodeId, array $data): int
    {
        $sql = "INSERT INTO qr_scans (qr_code_id, scanned_at, ip_hash, country, country_code, region, city, device_type, os, browser, referrer, user_agent)
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return Database::insertGetId($sql, [
            $qrCodeId,
            $data['ip_hash'],
            $data['country'] ?? 'Unknown',
            $data['country_code'] ?? 'XX',
            $data['region'] ?? 'Unknown',
            $data['city'] ?? 'Unknown',
            $data['device_type'] ?? 'Desktop',
            $data['os'] ?? 'Unknown',
            $data['browser'] ?? 'Unknown',
            substr($data['referrer'] ?? 'Direct', 0, 255),
            substr($data['user_agent'] ?? '', 0, 500),
        ]);
    }

    public static function getStatsForQr(int $qrCodeId, int $days = 30): array
    {
        // Total, Unique, and Today Scans
        $totals = Database::fetchOne(
            "SELECT COUNT(*) as total_scans,
                    COUNT(DISTINCT ip_hash) as unique_scans,
                    COALESCE(SUM(CASE WHEN scanned_at >= CURDATE() THEN 1 ELSE 0 END), 0) as today_scans
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$qrCodeId, $days]
        );

        // Timeline (Scans per day)
        $timeline = Database::fetchAll(
            "SELECT DATE(scanned_at) as scan_date, COUNT(*) as scan_count, COUNT(DISTINCT ip_hash) as unique_count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(scanned_at)
             ORDER BY scan_date ASC",
            [$qrCodeId, $days]
        );

        // Devices
        $devices = Database::fetchAll(
            "SELECT device_type, COUNT(*) as count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY device_type
             ORDER BY count DESC",
            [$qrCodeId, $days]
        );

        // OS
        $operatingSystems = Database::fetchAll(
            "SELECT os, COUNT(*) as count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY os
             ORDER BY count DESC
             LIMIT 10",
            [$qrCodeId, $days]
        );

        // Browsers
        $browsers = Database::fetchAll(
            "SELECT browser, COUNT(*) as count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY browser
             ORDER BY count DESC
             LIMIT 10",
            [$qrCodeId, $days]
        );

        // Countries
        $countries = Database::fetchAll(
            "SELECT country, country_code, COUNT(*) as count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY country, country_code
             ORDER BY count DESC
             LIMIT 10",
            [$qrCodeId, $days]
        );

        // Referrers
        $referrers = Database::fetchAll(
            "SELECT referrer, COUNT(*) as count
             FROM qr_scans
             WHERE qr_code_id = ? AND scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY referrer
             ORDER BY count DESC
             LIMIT 10",
            [$qrCodeId, $days]
        );

        // Recent Scans
        $recent = Database::fetchAll(
            "SELECT id, scanned_at, country, country_code, city, device_type, os, browser, referrer
             FROM qr_scans
             WHERE qr_code_id = ?
             ORDER BY id DESC
             LIMIT 25",
            [$qrCodeId]
        );

        return [
            'total_scans'       => (int)($totals['total_scans'] ?? 0),
            'today_scans'       => (int)($totals['today_scans'] ?? 0),
            'unique_scans'      => (int)($totals['unique_scans'] ?? 0),
            'timeline'          => $timeline,
            'devices'           => $devices,
            'operating_systems' => $operatingSystems,
            'browsers'          => $browsers,
            'countries'         => $countries,
            'referrers'         => $referrers,
            'recent'            => $recent,
        ];
    }

    public static function getUserOverviewStats(int $userId): array
    {
        $qrTotals = Database::fetchOne(
            "SELECT COUNT(CASE WHEN status != 'deleted' THEN 1 END) as total_qrs,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_qrs,
                    COALESCE(SUM(scan_count), 0) as total_scans
             FROM qr_codes
             WHERE user_id = ?",
            [$userId]
        );

        $todayScans = Database::fetchOne(
            "SELECT COUNT(*) as today_scans,
                    COUNT(DISTINCT s.ip_hash) as today_unique_scanners
             FROM qr_scans s
             JOIN qr_codes q ON s.qr_code_id = q.id
             WHERE q.user_id = ? AND s.scanned_at >= CURDATE()",
            [$userId]
        );

        $uniqueScanners = Database::fetchOne(
            "SELECT COUNT(DISTINCT s.ip_hash) as unique_scanners
             FROM qr_scans s
             JOIN qr_codes q ON s.qr_code_id = q.id
             WHERE q.user_id = ?",
            [$userId]
        );

        $recentTimeline = Database::fetchAll(
            "SELECT DATE(s.scanned_at) as scan_date, COUNT(*) as scan_count, COUNT(DISTINCT s.ip_hash) as unique_scanners
             FROM qr_scans s
             JOIN qr_codes q ON s.qr_code_id = q.id
             WHERE q.user_id = ? AND s.scanned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(s.scanned_at)
             ORDER BY scan_date ASC",
            [$userId]
        );

        $topQrs = Database::fetchAll(
            "SELECT id, name, short_code, scan_count, status, created_at
             FROM qr_codes
             WHERE user_id = ? AND status != 'deleted'
             ORDER BY scan_count DESC
             LIMIT 5",
            [$userId]
        );

        return [
            'total_qrs'              => (int)($qrTotals['total_qrs'] ?? 0),
            'active_qrs'             => (int)($qrTotals['active_qrs'] ?? 0),
            'total_scans'            => (int)($qrTotals['total_scans'] ?? 0),
            'today_scans'            => (int)($todayScans['today_scans'] ?? 0),
            'today_unique_scanners'  => (int)($todayScans['today_unique_scanners'] ?? 0),
            'unique_scanners'        => (int)($uniqueScanners['unique_scanners'] ?? 0),
            'recent_timeline'        => $recentTimeline,
            'top_qrs'                => $topQrs,
        ];
    }

    public static function getAdminOverviewStats(): array
    {
        $userCounts = Database::fetchOne("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active FROM users");
        $qrCounts   = Database::fetchOne("
            SELECT COUNT(CASE WHEN status != 'deleted' THEN 1 END) as total,
                   SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
                   COALESCE(SUM(scan_count), 0) as total_scans
            FROM qr_codes
        ");
        
        $todayScans = Database::fetchOne(
            "SELECT COUNT(*) as today_scans,
                    COUNT(DISTINCT s.ip_hash) as today_unique_scanners
             FROM qr_scans s
             WHERE s.scanned_at >= CURDATE()"
        );

        $uniqueScanners = Database::fetchOne(
            "SELECT COUNT(DISTINCT s.ip_hash) as unique_scanners
             FROM qr_scans s"
        );

        $timeline = Database::fetchAll(
            "SELECT DATE(s.scanned_at) as scan_date, COUNT(*) as scan_count, COUNT(DISTINCT s.ip_hash) as unique_scanners
             FROM qr_scans s
             WHERE s.scanned_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY DATE(s.scanned_at)
             ORDER BY scan_date ASC"
        );

        return [
            'total_users'            => (int)($userCounts['total'] ?? 0),
            'active_users'           => (int)($userCounts['active'] ?? 0),
            'total_qrs'              => (int)($qrCounts['total'] ?? 0),
            'active_qrs'             => (int)($qrCounts['active'] ?? 0),
            'total_scans'            => (int)($qrCounts['total_scans'] ?? 0),
            'today_scans'            => (int)($todayScans['today_scans'] ?? 0),
            'today_unique_scanners'  => (int)($todayScans['today_unique_scanners'] ?? 0),
            'unique_scanners'        => (int)($uniqueScanners['unique_scanners'] ?? 0),
            'timeline'               => $timeline,
        ];
    }
}
