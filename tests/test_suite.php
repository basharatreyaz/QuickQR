<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;
use App\Helpers\Security;
use App\Models\User;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\QrScan;
use App\QR\QrGenerator;
use App\Services\QrService;
use App\Services\AnalyticsService;

Config::load();

function assertTest(bool $condition, string $title) {
    if ($condition) {
        echo "[PASS] {$title}\n";
    } else {
        echo "[FAIL] {$title}\n";
        exit(1);
    }
}

echo "=== STARTING QUICKQR SAAS TEST SUITE ===\n\n";

// 1. Database & Config
$pdo = Database::connect();
assertTest($pdo instanceof PDO, "Database PDO Connection succeeded");

// 2. Security & Password Hashing
$pass = 'secret123';
$hash = Security::hashPassword($pass);
assertTest(Security::verifyPassword($pass, $hash), "Password hash and verify works");
assertTest(!Security::verifyPassword('wrong', $hash), "Password verify rejects wrong password");

$ipHash = Security::hashIp('192.168.1.100');
assertTest(strlen($ipHash) === 64, "IP hash anonymization generates 64-char SHA256");

$code = Security::generateShortCode(8);
assertTest(strlen($code) === 8, "Short code generated with length 8: {$code}");

// 3. Destination URL Security Validation
assertTest(Security::validateDestinationUrl('https://google.com'), "Valid HTTPS URL accepted");
assertTest(Security::validateDestinationUrl('http://sub.domain.com/path?foo=bar#hash'), "Valid HTTP URL accepted");
assertTest(!Security::validateDestinationUrl('javascript:alert(1)'), "Security rejects javascript: open-redirect vector");
assertTest(!Security::validateDestinationUrl('data:text/html,bad'), "Security rejects data: URI vector");
assertTest(!Security::validateDestinationUrl('file:///etc/passwd'), "Security rejects file: URI vector");
assertTest(!Security::validateDestinationUrl('not a url'), "Security rejects malformed string as URL");

// 4. Plans & Users
$plans = Plan::getAll();
assertTest(count($plans) >= 3, "Found default SaaS plans (Free, Pro, Business)");

$adminUser = User::findByEmail('admin@example.com');
assertTest($adminUser !== null && $adminUser['role'] === 'admin', "Default Admin account found");

$demoUser = User::findByEmail('demo@example.com');
assertTest($demoUser !== null && $demoUser['role'] === 'user', "Default Demo User found");

// 5. QR Code Generator (SVG & PNG)
$generator = new QrGenerator();
$svg = $generator->renderSvg('https://example.com/test', [
    'fg_color' => '#4338ca',
    'bg_color' => '#f8fafc',
    'margin'   => 2,
    'style'    => 'rounded',
    'ecc'      => 'M',
]);
assertTest(str_contains($svg, '<svg') && str_contains($svg, '</svg>'), "SVG QR code rendered successfully");
assertTest(str_contains($svg, '#4338ca'), "SVG contains custom foreground color");

$png = $generator->renderPng('https://example.com/test', [
    'fg_color' => '#15803d',
    'bg_color' => '#ffffff',
    'margin'   => 2,
    'style'    => 'square',
    'ecc'      => 'H',
]);
assertTest(strlen($png) > 100 && str_starts_with($png, "\x89PNG"), "PNG QR binary generated with valid PNG signature");

// 6. QR Creation, Retrieval, Duplication, and Deletion
Database::execute("UPDATE qr_codes SET status = 'deleted' WHERE user_id = ? AND (name LIKE 'Automated Test%' OR name LIKE 'Default Style%' OR name LIKE 'Scan Retention%')", [$demoUser['id']]);

$createdQr = QrService::create($demoUser['id'], [
    'name'        => 'Automated Test QR',
    'type'        => 'url',
    'destination' => 'https://example.com/automated-test',
    'config'      => [
        'fg_color' => '#2563eb',
        'bg_color' => '#ffffff',
        'style'    => 'dots',
        'margin'   => 2
    ]
]);
assertTest(!empty($createdQr['id']) && !empty($createdQr['short_code']), "QR created with ID {$createdQr['id']} and short code {$createdQr['short_code']}");

$defaultStyleQr = QrService::create($demoUser['id'], [
    'name'        => 'Default Style Test QR',
    'type'        => 'url',
    'destination' => 'https://example.com/style-test',
    'config'      => []
]);
assertTest($defaultStyleQr['config']['style'] === 'dots', "QR code created with omitted style defaults to 'dots'");

$fetched = QrCode::findById($createdQr['id'], $demoUser['id']);
assertTest($fetched['name'] === 'Automated Test QR', "QR fetched from database correctly");

$updated = QrService::update($createdQr['id'], $demoUser['id'], [
    'destination' => 'https://example.com/updated-url',
    'name'        => 'Automated Test QR (Updated)'
]);
assertTest($updated['destination'] === 'https://example.com/updated-url', "QR destination successfully updated");

$duplicated = QrService::duplicate($createdQr['id'], $demoUser['id']);
assertTest($duplicated['name'] === 'Automated Test QR (Updated) (Copy)', "QR duplicated successfully");

// 7. QR Scan Tracking and Analytics
$initialScans = (int)$fetched['scan_count'];
AnalyticsService::trackScan($createdQr['id']);
$refetched = QrCode::findById($createdQr['id']);
assertTest((int)$refetched['scan_count'] === $initialScans + 1, "Scan incremented scan count correctly");

$stats = QrScan::getStatsForQr($createdQr['id']);
assertTest($stats['total_scans'] >= 1, "Analytics recorded scan in qr_scans table");
assertTest(!empty($stats['devices']), "Device breakdown generated in analytics");

// Clean up test QR
QrService::delete($duplicated['id'], $demoUser['id']);
QrService::delete($createdQr['id'], $demoUser['id']);
$deletedCheck = QrCode::findById($createdQr['id'], $demoUser['id']);
assertTest($deletedCheck === null, "Deleted QR is soft-deleted and filtered out");

// 8. Security & Request Method Enhancements
assertTest(!Security::validateDestinationUrl("https://example.com\r\nSet-Cookie:malicious=1"), "Security rejects CRLF / response-splitting injection");
assertTest(!Security::validateDestinationUrl("https://example.com\nLocation:bad"), "Security rejects newline injection in URL");
assertTest(is_array(\App\Helpers\Request::json()), "Request::json() alias exists and returns array");

// 9. Composite Index & Telemetry Verification
$scansIndex = Database::fetchAll("SHOW INDEX FROM qr_scans WHERE Key_name = 'idx_qr_scanned_at'");
assertTest(!empty($scansIndex), "Database contains composite index idx_qr_scanned_at for high-performance telemetry");

$qrStats = Database::fetchOne("SELECT COUNT(*) as total FROM qr_codes WHERE status != 'deleted'");
assertTest(isset($qrStats['total']), "Public telemetry aggregation executes cleanly");

// 10. High-Performance Cache Subsystem (Redis & Fallback Drivers)
use App\Cache\Cache;
assertTest(Cache::isAvailable(), "Cache subsystem is available and operational (Driver: " . Cache::getDriverName() . ")");

Cache::set('test_key', ['foo' => 'bar', 'count' => 42], 60);
$cachedVal = Cache::get('test_key');
assertTest(is_array($cachedVal) && $cachedVal['count'] === 42, "Cache set and get works correctly");

$remembered = Cache::remember('test_remember', 60, function () {
    return 'computed_value';
});
assertTest($remembered === 'computed_value', "Cache remember computes and stores value");

$incremented = Cache::increment('test_counter', 5, 60);
assertTest($incremented >= 5, "Cache atomic increment works (Count: {$incremented})");

Cache::delete('test_key');
assertTest(Cache::get('test_key') === null, "Cache key deletion cleans up key");

// 11. Admin Override & Cache Invalidation Verification
$adminTestQr = QrService::create($demoUser['id'], [
    'name' => 'Admin Override Test',
    'type' => 'url',
    'destination' => 'https://example.com/admin-override-test'
]);
assertTest($adminTestQr['id'] > 0, "Created test QR for admin override");

// Verify Admin (userId = null) can find and toggle status
$adminFound = QrCode::findById($adminTestQr['id'], null);
assertTest($adminFound !== null && $adminFound['id'] === $adminTestQr['id'], "Admin (userId = null) can inspect any user's QR");

$toggled = QrService::toggleStatus($adminTestQr['id'], null);
assertTest($toggled['status'] === 'paused', "Admin (userId = null) can pause any user's QR");

$updated = QrService::update($adminTestQr['id'], null, ['name' => 'Admin Renamed QR']);
assertTest($updated['name'] === 'Admin Renamed QR', "Admin (userId = null) can update any user's QR");

// Invalidate & delete
Cache::set('telemetry:public_stats', ['dummy' => 1], 60);
QrService::delete($adminTestQr['id'], null);
assertTest(Cache::get('telemetry:public_stats') === null, "QrService::delete correctly invalidates telemetry:public_stats cache");
assertTest(QrCode::findById($adminTestQr['id'], null) === null, "Admin (userId = null) can soft-delete any user's QR");

// 12. Scan Retention on Deleted QR Codes Verification
$retentionQr = QrService::create($demoUser['id'], [
    'name' => 'Scan Retention Test',
    'type' => 'url',
    'destination' => 'https://example.com/scan-retention'
]);
// Record a scan
QrCode::incrementScanCount($retentionQr['id']);
QrScan::recordScan($retentionQr['id'], [
    'ip_hash'     => Security::hashIp('127.0.0.1'),
    'country'     => 'Local',
    'device_type' => 'Desktop',
    'os'          => 'Windows',
    'browser'     => 'Chrome'
]);

$preStats = QrScan::getUserOverviewStats($demoUser['id']);
$preAdminStats = QrScan::getAdminOverviewStats();

// Soft-delete the QR
QrService::delete($retentionQr['id'], $demoUser['id']);

$postStats = QrScan::getUserOverviewStats($demoUser['id']);
$postAdminStats = QrScan::getAdminOverviewStats();

assertTest($postStats['total_scans'] === $preStats['total_scans'], "User Total Scans Recorded remains intact after QR deletion");
assertTest($postStats['today_scans'] === $preStats['today_scans'], "User Scans Today remains intact after QR deletion");
assertTest($postAdminStats['total_scans'] === $preAdminStats['total_scans'], "Admin Total Scans Recorded remains intact after QR deletion");
assertTest($postAdminStats['today_scans'] === $preAdminStats['today_scans'], "Admin Scans Today remains intact after QR deletion");

// 13. Social QR Code URL Formatter Verification
use App\QR\QrContentFormatter;
assertTest(QrContentFormatter::format('social', ['platform' => 'youtube', 'handle' => 'quickqr']) === 'https://youtube.com/@quickqr', 'YouTube social handle formatted with @');
assertTest(QrContentFormatter::format('social', ['platform' => 'linkedin', 'handle' => 'quickqr']) === 'https://linkedin.com/in/quickqr', 'LinkedIn social handle formatted with /in/');
assertTest(QrContentFormatter::format('social', ['platform' => 'x', 'handle' => 'quickqr']) === 'https://x.com/quickqr', 'X/Twitter social handle formatted with x.com');

// 14. Password Reset Token Security Verification
$testResetToken = bin2hex(random_bytes(32));
User::setResetToken($demoUser['id'], $testResetToken, 3600);
$foundByToken = User::findByResetToken($testResetToken);
assertTest($foundByToken !== null && $foundByToken['id'] === $demoUser['id'], "User successfully looked up via active reset token");

User::clearResetToken($demoUser['id']);
$clearedCheck = User::findByResetToken($testResetToken);
assertTest($clearedCheck === null, "Reset token is successfully revoked after consumption");

// 15. Production Health Check Subsystem
$healthRow = Database::fetchOne('SELECT 1 as ping');
assertTest((int)($healthRow['ping'] ?? 0) === 1, "Database health probe succeeds");

// 16. Contact Form Inquiry Audit Logging
use App\Models\AuditLog;
AuditLog::log('contact_inquiry_received', null, ['name' => 'Test User', 'email' => 'test@example.com', 'subject' => 'General Inquiry']);
$recentLogs = AuditLog::getRecent(5);
$foundContactLog = false;
foreach ($recentLogs as $log) {
    if ($log['action'] === 'contact_inquiry_received') {
        $foundContactLog = true;
    }
}
assertTest($foundContactLog, "Contact inquiry audit log successfully registered");

// 17. Security Utilities Verification
$resolvedIp = \App\Helpers\Request::ip();
assertTest(filter_var($resolvedIp, FILTER_VALIDATE_IP) !== false, "Request::ip returns a valid IP address ({$resolvedIp})");
assertTest(Security::sanitize('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;', "Security::sanitize properly escapes XSS vectors");

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";

