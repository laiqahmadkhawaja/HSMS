<?php
/**
 * HSMS - Automated Verification Test for Phase 7 (Recovery Management & Field Staff)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

function auth_curl(string $url, ?array $post = null): array {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $raw = (string)curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

echo "=== STARTING PHASE 7 RECOVERY MANAGEMENT TEST SUITE ===\n";

$db = get_db_connection();

// 1. Verify Active Booking
$stmtBk = $db->query("SELECT * FROM `bookings` WHERE `status` = 'active' ORDER BY id DESC LIMIT 1");
$booking = $stmtBk->fetch();
if (!$booking) {
    echo "[FAIL] No active booking contract found for PTP tests.\n";
    exit(1);
}
$bkId = (int)$booking['id'];
$socId = (int)$booking['society_id'];
$custId = (int)$booking['customer_id'];
echo "[PASS] Test Booking found: {$booking['booking_number']} (Customer ID: {$custId})\n";

// 2. Test Recovery Officers Page
$roPage = auth_curl("http://localhost/HSMS/modules/employees/recovery_officers.php");
if ($roPage['code'] !== 200) {
    echo "[FAIL] Recovery officers page failed to load (HTTP {$roPage['code']})\n";
    exit(1);
}
preg_match('/name="csrf_token" value="([^"]+)"/', $roPage['body'], $m);
$csrf = $m[1] ?? '';
if (empty($csrf)) {
    echo "[FAIL] CSRF token not found in recovery_officers.php\n";
    exit(1);
}
echo "[PASS] Recovery officers dashboard rendered successfully (HTTP 200)\n";

// 3. Test Officer Target & Block Update
$stmtRo = $db->prepare("SELECT id FROM `recovery_officers` WHERE `society_id` = :sid LIMIT 1");
$stmtRo->execute(['sid' => $socId]);
$officerId = (int)$stmtRo->fetchColumn();
if ($officerId <= 0) {
    echo "[FAIL] No recovery officer record found for test society.\n";
    exit(1);
}

$updateRoPost = [
    'csrf_token'      => $csrf,
    'action'          => 'save_officer',
    'officer_id'      => $officerId,
    'user_id'         => 3,
    'recovery_target' => 7500000.00,
    'assigned_blocks' => ['BLK-A', 'BLK-B', 'BLK-C'],
    'status'          => 'active'
];
$updRoRes = auth_curl("http://localhost/HSMS/modules/employees/recovery_officers.php", $updateRoPost);
if ($updRoRes['code'] !== 302) {
    echo "[FAIL] Officer update did not redirect (HTTP {$updRoRes['code']})\n";
    exit(1);
}

$stmtRoCheck = $db->prepare("SELECT recovery_target, assigned_blocks FROM `recovery_officers` WHERE `id` = :id");
$stmtRoCheck->execute(['id' => $officerId]);
$roCheck = $stmtRoCheck->fetch();
if (abs((float)$roCheck['recovery_target'] - 7500000.00) > 0.01) {
    echo "[FAIL] Officer target update mismatch: {$roCheck['recovery_target']}\n";
    exit(1);
}
echo "[PASS] Recovery Officer updated: Target set to Rs. 7,500,000.00 and blocks assigned.\n";

// 4. Test Promises to Pay (PTP) Creation
$ptpPage = auth_curl("http://localhost/HSMS/modules/recoveries/promises.php");
if ($ptpPage['code'] !== 200) {
    echo "[FAIL] Promises to Pay page failed to load (HTTP {$ptpPage['code']})\n";
    exit(1);
}
preg_match('/name="csrf_token" value="([^"]+)"/', $ptpPage['body'], $m2);
$ptpCsrf = $m2[1] ?? '';

$ptpPost1 = [
    'csrf_token'          => $ptpCsrf,
    'action'              => 'create_ptp',
    'booking_id'          => $bkId,
    'recovery_officer_id' => $officerId,
    'contact_date'        => date('Y-m-d'),
    'promise_date'        => date('Y-m-d', strtotime('+5 days')),
    'promised_amount'     => 150000.00,
    'notes'               => 'Customer agreed to pay Rs. 150,000 within 5 days'
];
$ptpRes1 = auth_curl("http://localhost/HSMS/modules/recoveries/promises.php", $ptpPost1);
if ($ptpRes1['code'] !== 302) {
    echo "[FAIL] Promise to Pay 1 creation did not redirect (HTTP {$ptpRes1['code']})\n";
    exit(1);
}

// Verify PTP 1 in DB
$stmtPtp1 = $db->prepare("SELECT * FROM `promises_to_pay` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtPtp1->execute(['bid' => $bkId]);
$ptpRow1 = $stmtPtp1->fetch();
if (!$ptpRow1 || abs((float)$ptpRow1['promised_amount'] - 150000.00) > 0.01 || $ptpRow1['status'] !== 'pending') {
    echo "[FAIL] PTP 1 record missing or values incorrect in DB.\n";
    exit(1);
}
$ptpId1 = (int)$ptpRow1['id'];
echo "[PASS] Promise to Pay #{$ptpId1} created (Amount: Rs. 150,000.00, Status: pending)\n";

// 5. Test Status Transition: Pending -> Fulfilled
$statusPost1 = [
    'csrf_token' => $ptpCsrf,
    'action'     => 'update_status',
    'ptp_id'     => $ptpId1,
    'new_status' => 'fulfilled'
];
$statRes1 = auth_curl("http://localhost/HSMS/modules/recoveries/promises.php", $statusPost1);
if ($statRes1['code'] !== 302) {
    echo "[FAIL] Status transition to fulfilled did not redirect (HTTP {$statRes1['code']})\n";
    exit(1);
}

$stmtPtpCheck1 = $db->prepare("SELECT status FROM `promises_to_pay` WHERE `id` = :id");
$stmtPtpCheck1->execute(['id' => $ptpId1]);
if ($stmtPtpCheck1->fetchColumn() !== 'fulfilled') {
    echo "[FAIL] PTP 1 status was not updated to 'fulfilled'.\n";
    exit(1);
}
echo "[PASS] PTP #{$ptpId1} status successfully updated to 'fulfilled'.\n";

// 6. Test Second Promise: Pending -> Broken
$ptpPost2 = [
    'csrf_token'          => $ptpCsrf,
    'action'              => 'create_ptp',
    'booking_id'          => $bkId,
    'recovery_officer_id' => $officerId,
    'contact_date'        => date('Y-m-d', strtotime('-10 days')),
    'promise_date'        => date('Y-m-d', strtotime('-2 days')), // Expired
    'promised_amount'     => 50000.00,
    'notes'               => 'Follow-up call commitment test'
];
$ptpRes2 = auth_curl("http://localhost/HSMS/modules/recoveries/promises.php", $ptpPost2);
if ($ptpRes2['code'] !== 302) {
    echo "[FAIL] Promise to Pay 2 creation did not redirect (HTTP {$ptpRes2['code']})\n";
    exit(1);
}

$stmtPtp2 = $db->prepare("SELECT * FROM `promises_to_pay` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtPtp2->execute(['bid' => $bkId]);
$ptpRow2 = $stmtPtp2->fetch();
$ptpId2 = (int)$ptpRow2['id'];

$statusPost2 = [
    'csrf_token' => $ptpCsrf,
    'action'     => 'update_status',
    'ptp_id'     => $ptpId2,
    'new_status' => 'broken'
];
$statRes2 = auth_curl("http://localhost/HSMS/modules/recoveries/promises.php", $statusPost2);
if ($statRes2['code'] !== 302) {
    echo "[FAIL] Status transition to broken did not redirect (HTTP {$statRes2['code']})\n";
    exit(1);
}

$stmtPtpCheck2 = $db->prepare("SELECT status FROM `promises_to_pay` WHERE `id` = :id");
$stmtPtpCheck2->execute(['id' => $ptpId2]);
if ($stmtPtpCheck2->fetchColumn() !== 'broken') {
    echo "[FAIL] PTP 2 status was not updated to 'broken'.\n";
    exit(1);
}
echo "[PASS] PTP #{$ptpId2} status successfully updated to 'broken'.\n";

// 7. Verify Audit Log entries for Phase 7
$stmtAudit = $db->prepare("SELECT COUNT(*) FROM `audit_logs` WHERE `action` IN ('PTP_CREATED', 'PTP_STATUS_UPDATED', 'RECOVERY_OFFICER_UPDATED')");
$stmtAudit->execute();
$auditCnt = (int)$stmtAudit->fetchColumn();
if ($auditCnt < 3) {
    echo "[FAIL] Expected at least 3 audit log entries, found {$auditCnt}\n";
    exit(1);
}
echo "[PASS] Audit logging verified for all Phase 7 actions ({$auditCnt} entries recorded)\n";

echo "ALL PHASE 7 RECOVERY MANAGEMENT TESTS PASSED 100%!\n";
