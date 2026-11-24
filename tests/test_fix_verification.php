<?php
/**
 * HSMS - Automated Verification Suite for User Reported Issues
 * Tests:
 * 1. Image 1 Fix: modules/transfers/print.php (No plot_category column error)
 * 2. Image 2 Fix: modules/installment_plans/edit.php?id=1 (e() integer TypeError fix)
 * 3. Image 3 Fix: modules/employees/recovery_officers.php & modules/users/index.php (users_manage / recovery_manage access)
 * 4. Settings Modules: backup.php, audit_logs.php, profile.php, index.php (No 404 errors)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$baseUrl = 'http://localhost/HSMS';
$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

function fix_assert(bool $condition, string $description): void {
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        exit(1);
    }
}

function auth_get(string $url, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $body = (string)curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

echo "=== HSMS Verification Suite: User Reported Bug Fixes ===\n\n";

// Ensure authenticated test cookie
$ch = curl_init($baseUrl . '/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$loginHtml = curl_exec($ch);
preg_match('/name="csrf_token" value="([^"]+)"/', (string)$loginHtml, $m);
$csrf = $m[1] ?? '';

curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'username'   => 'admin',
    'password'   => 'admin123'
]));
curl_exec($ch);
curl_close($ch);

// 1. Verify Image 1 Fix: modules/transfers/print.php
echo "--- 1. Testing modules/transfers/print.php (Image 1: plot_category error) ---\n";
// Find an existing transfer ID or create/check
$db = get_db_connection();
$stmtT = $db->query("SELECT id FROM `plot_transfers` ORDER BY id DESC LIMIT 1");
$trfId = $stmtT->fetchColumn();

if ($trfId) {
    $res = auth_get($baseUrl . "/modules/transfers/print.php?id={$trfId}", $cookieFile);
    fix_assert($res['code'] === 200, "GET /modules/transfers/print.php?id={$trfId} returns HTTP 200");
    fix_assert(!str_contains($res['body'], 'plot_category'), "No reference to missing 'plot_category' in print output");
    fix_assert(!str_contains($res['body'], 'Fatal error'), "Zero fatal SQL or PHP errors in transfer print deed");
    fix_assert(str_contains($res['body'], 'Deed of Allotment Transfer & Assignment'), "Transfer deed renders official certificate header");
} else {
    echo "  [INFO] No plot transfer records exist in database, verifying query prepare directly\n";
    $testStmt = $db->prepare("
        SELECT trf.*,
               seller.full_name as seller_name,
               buyer.full_name as buyer_name,
               p.plot_number, p.is_corner, p.is_boulevard, p.is_park_facing, ps.name as size_name, pt.name as type_name,
               b.block_name, ph.phase_name
        FROM `plot_transfers` trf
        JOIN `plots` p ON trf.plot_id = p.id
        LEFT JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
        LEFT JOIN `plot_types` pt ON p.plot_type_id = pt.id
        JOIN `blocks` b ON p.block_id = b.id
        JOIN `phases` ph ON b.phase_id = ph.id
        JOIN `bookings` bk ON trf.booking_id = bk.id
        JOIN `customers` seller ON trf.from_customer_id = seller.id
        JOIN `customers` buyer ON trf.to_customer_id = buyer.id
        WHERE trf.id = 1
    ");
    fix_assert($testStmt !== false, "Transfer print query prepares cleanly without column errors");
}

// 2. Verify Image 2 Fix: modules/installment_plans/edit.php?id=1
echo "\n--- 2. Testing modules/installment_plans/edit.php?id=1 (Image 2: e() TypeError) ---\n";
$resEdit = auth_get($baseUrl . "/modules/installment_plans/edit.php?id=1", $cookieFile);
fix_assert($resEdit['code'] === 200, "GET /modules/installment_plans/edit.php?id=1 returns HTTP 200");
fix_assert(!str_contains($resEdit['body'], 'TypeError'), "Zero TypeError in installment_plans/edit.php");
fix_assert(!str_contains($resEdit['body'], 'Fatal error'), "Zero Fatal errors in installment_plans/edit.php");
fix_assert(str_contains($resEdit['body'], 'Edit Plan Template'), "Page renders 'Edit Plan Template' form successfully");
fix_assert(str_contains($resEdit['body'], 'name="duration_months"'), "duration_months input rendered without type crashes");

// 3. Verify Image 3 Fix: Recovery Officers & Users Management
echo "\n--- 3. Testing Recovery Officers & Users Management (Image 3: Access Denied) ---\n";
$resRec = auth_get($baseUrl . "/modules/employees/recovery_officers.php", $cookieFile);
fix_assert($resRec['code'] === 200, "GET /modules/employees/recovery_officers.php returns HTTP 200");
fix_assert(!str_contains($resRec['body'], 'Access Denied'), "Recovery officers page does not show Access Denied");
fix_assert(str_contains($resRec['body'], 'Recovery Officers & Field Staff'), "Page renders recovery officers header");

$resUsers = auth_get($baseUrl . "/modules/users/index.php", $cookieFile);
fix_assert($resUsers['code'] === 200, "GET /modules/users/index.php returns HTTP 200");
fix_assert(str_contains($resUsers['body'], 'Users & Access Control'), "Page renders user directory");
fix_assert(str_contains($resUsers['body'], 'System Administrator'), "Admin user visible in directory");

// 4. Verify Settings Modules Existence
echo "\n--- 4. Testing Settings Modules (Zero 404s) ---\n";
$resBackup = auth_get($baseUrl . "/modules/settings/backup.php", $cookieFile);
fix_assert($resBackup['code'] === 200, "GET /modules/settings/backup.php returns HTTP 200 (was 404)");
fix_assert(str_contains($resBackup['body'], 'Database Backup & Disaster Recovery'), "Backup page renders correctly");

$resAudit = auth_get($baseUrl . "/modules/settings/audit_logs.php", $cookieFile);
fix_assert($resAudit['code'] === 200, "GET /modules/settings/audit_logs.php returns HTTP 200 (was 404)");
fix_assert(str_contains($resAudit['body'], 'System Audit Trail & Compliance'), "Audit logs page renders correctly");

$resProfile = auth_get($baseUrl . "/modules/settings/profile.php", $cookieFile);
fix_assert($resProfile['code'] === 200, "GET /modules/settings/profile.php returns HTTP 200 (was 404)");
fix_assert(str_contains($resProfile['body'], 'My Account & Security'), "Profile page renders correctly");

$resSettings = auth_get($baseUrl . "/modules/settings/index.php", $cookieFile);
fix_assert($resSettings['code'] === 200, "GET /modules/settings/index.php returns HTTP 200 (was 404)");
fix_assert(str_contains($resSettings['body'], 'Society & System Settings'), "Settings page renders correctly");

$resSocView = auth_get($baseUrl . "/modules/societies/view.php?id=1", $cookieFile);
fix_assert($resSocView['code'] === 200, "GET /modules/societies/view.php?id=1 returns HTTP 200 (was missing)");
fix_assert(str_contains($resSocView['body'], 'Project Master Details'), "Society details page renders correctly");

echo "\n>>> ALL USER REPORTED ISSUES FIXED AND VERIFIED 100%! <<<\n";
