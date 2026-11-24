<?php
/**
 * HSMS - Automated Verification Test for Phase 5 (Booking & Sales)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/booking_service.php';

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

echo "=== STARTING PHASE 5 BOOKING & SALES TEST SUITE ===\n";

$db = get_db_connection();

// Reset test plot and any previous test booking on TEST-P2-01
$db->exec("
    DELETE FROM `payment_reversals` WHERE `payment_id` IN (SELECT id FROM `payments` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01')));
    DELETE FROM `payment_allocations` WHERE `payment_id` IN (SELECT id FROM `payments` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01')));
    DELETE FROM `payments` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `plot_transfers` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `promises_to_pay` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `cancellations` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `customer_ledger` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01');
    DELETE FROM `installment_schedules` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `installment_revisions` WHERE `booking_id` IN (SELECT id FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01'));
    DELETE FROM `bookings` WHERE `plot_id` IN (SELECT id FROM `plots` WHERE `plot_number` = 'TEST-P2-01');
    UPDATE `plots` SET `status` = 'available' WHERE `plot_number` = 'TEST-P2-01';
");

// 1. Ensure test customer exists
$stmtCust = $db->query("SELECT id, full_name FROM `customers` WHERE `status` = 'active' ORDER BY id ASC LIMIT 1");
$customer = $stmtCust->fetch();
if (!$customer) {
    echo "[FAIL] No active customer found for test.\n";
    exit(1);
}
$custId = (int)$customer['id'];
echo "[PASS] Test Buyer selected: {$customer['full_name']} (ID: {$custId})\n";

// 2. Ensure an available plot exists
$stmtPlot = $db->query("SELECT id, plot_number, base_price, development_charges, additional_charges FROM `plots` WHERE `status` = 'available' ORDER BY id ASC LIMIT 1");
$plot = $stmtPlot->fetch();
if (!$plot) {
    echo "[FAIL] No available plot found for test.\n";
    exit(1);
}
$plotId = (int)$plot['id'];
echo "[PASS] Test Plot selected: Plot #{$plot['plot_number']} (ID: {$plotId})\n";

// 3. Fetch CSRF token from booking create screen
$createPage = auth_curl("http://localhost/HSMS/modules/bookings/create.php?plot_id={$plotId}&customer_id={$custId}");
preg_match('/name="csrf_token" value="([^"]+)"/', $createPage['body'], $m);
$csrf = $m[1] ?? '';
if (empty($csrf)) {
    echo "[FAIL] CSRF token not found in bookings/create.php\n";
    exit(1);
}

// 4. Submit Plot Booking
$basePrice = 2700000.00;
$devCharges = 400000.00;
$extraCharges = 100000.00;
$discount = 100000.00;
$netPrice = ($basePrice + $devCharges + $extraCharges) - $discount; // 3,100,000.00
$downPayment = 620000.00; // 20%

$bkPost = [
    'csrf_token'          => $csrf,
    'plot_id'             => $plotId,
    'customer_id'         => $custId,
    'booking_date'        => date('Y-m-d'),
    'base_price'          => $basePrice,
    'development_charges' => $devCharges,
    'extra_charges'       => $extraCharges,
    'discount_amount'     => $discount,
    'down_payment_amount' => $downPayment,
    'total_installments'  => 24,
    'frequency'           => 'monthly',
    'due_day'             => 10,
    'remarks'             => 'Automated test sales contract'
];

$bkRes = auth_curl("http://localhost/HSMS/modules/bookings/create.php", $bkPost);
if ($bkRes['code'] !== 302) {
    preg_match('/alert alert-danger.*?>(.*?)<\/div>/s', $bkRes['body'], $errAlert);
    preg_match_all('/invalid-feedback.*?>(.*?)<\/div>/s', $bkRes['body'], $errFeedback);
    echo "[DEBUG ERROR ALERT]: " . trim(strip_tags($errAlert[1] ?? 'None')) . "\n";
    echo "[DEBUG FEEDBACK ERRORS]: " . implode(' | ', array_map('trim', array_map('strip_tags', $errFeedback[1] ?? []))) . "\n";
    echo "[FAIL] Booking creation did not redirect (HTTP {$bkRes['code']})\n";
    exit(1);
}
echo "[PASS] Booking creation succeeded (HTTP 302 redirect)\n";

// 5. Verify Database Records
$stmtBk = $db->prepare("SELECT * FROM `bookings` WHERE `plot_id` = :pid ORDER BY id DESC LIMIT 1");
$stmtBk->execute(['pid' => $plotId]);
$booking = $stmtBk->fetch();
if (!$booking) {
    echo "[FAIL] Booking record not found in database.\n";
    exit(1);
}

$bkId = (int)$booking['id'];
echo "[PASS] Booking record verified in database (ID: {$bkId}, Number: {$booking['booking_number']})\n";

if (abs((float)$booking['net_sale_price'] - $netPrice) > 0.01) {
    echo "[FAIL] Booking net_sale_price mismatch: {$booking['net_sale_price']} vs {$netPrice}\n";
    exit(1);
}
echo "[PASS] Net sale price matches exact calculation: Rs. " . number_format($netPrice, 2) . "\n";

// Verify Plot status updated to 'booked'
$stmtPCheck = $db->prepare("SELECT status FROM `plots` WHERE `id` = :pid");
$stmtPCheck->execute(['pid' => $plotId]);
$newPlotStatus = $stmtPCheck->fetchColumn();
if ($newPlotStatus !== 'booked') {
    echo "[FAIL] Plot status was not updated to 'booked' (got '{$newPlotStatus}')\n";
    exit(1);
}
echo "[PASS] Plot status successfully updated to 'booked'\n";

// Verify Installment Schedule
$stmtSched = $db->prepare("SELECT COUNT(*) as cnt, SUM(amount_due) as total_sched FROM `installment_schedules` WHERE `booking_id` = :bid");
$stmtSched->execute(['bid' => $bkId]);
$schedStats = $stmtSched->fetch();
if ((int)$schedStats['cnt'] !== 25) { // 1 DP + 24 installments
    echo "[FAIL] Expected 25 schedule rows, got {$schedStats['cnt']}\n";
    exit(1);
}
if (abs((float)$schedStats['total_sched'] - $netPrice) > 0.01) {
    echo "[FAIL] Schedule sum mismatch: {$schedStats['total_sched']} vs {$netPrice}\n";
    exit(1);
}
echo "[PASS] Installment Schedule verified: 25 rows totaling exactly Rs. " . number_format($netPrice, 2) . "\n";

// Verify Customer Ledger Entry
$stmtLedger = $db->prepare("SELECT * FROM `customer_ledger` WHERE `booking_id` = :bid AND `transaction_type` = 'booking_debit'");
$stmtLedger->execute(['bid' => $bkId]);
$ledgerEntry = $stmtLedger->fetch();
if (!$ledgerEntry || abs((float)$ledgerEntry['debit'] - $netPrice) > 0.01) {
    echo "[FAIL] Customer ledger booking debit entry missing or incorrect.\n";
    exit(1);
}
echo "[PASS] Customer Ledger entry verified (Debit: Rs. " . number_format((float)$ledgerEntry['debit'], 2) . ", Running Balance: Rs. " . number_format((float)$ledgerEntry['running_balance'], 2) . ")\n";

// 6. Test Double-Booking Prevention
$dupBkRes = auth_curl("http://localhost/HSMS/modules/bookings/create.php", $bkPost);
if (!str_contains($dupBkRes['body'], 'is currently not available')) {
    echo "[FAIL] Double booking of already booked plot was not prevented!\n";
    exit(1);
}
echo "[PASS] Double booking of booked plot correctly prevented by database row lock.\n";

// 7. Test Schedule Revision
$revPost = [
    'csrf_token'             => $csrf,
    'action'                 => 'revise_schedule',
    'new_installments_count' => 12,
    'new_frequency'          => 'monthly',
    'new_start_date'         => date('Y-m-d', strtotime('+1 month')),
    'new_due_day'            => 15,
    'revision_reason'        => 'Phase 5 automated test schedule revision'
];
$revRes = auth_curl("http://localhost/HSMS/modules/bookings/view.php?id={$bkId}", $revPost);
if ($revRes['code'] !== 302) {
    echo "[FAIL] Schedule revision did not redirect (HTTP {$revRes['code']})\n";
    exit(1);
}

// Verify revision snapshot in database
$stmtRev = $db->prepare("SELECT * FROM `installment_revisions` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtRev->execute(['bid' => $bkId]);
$revRow = $stmtRev->fetch();
if (!$revRow || $revRow['revision_number'] != 1 || empty($revRow['snapshot_json'])) {
    echo "[FAIL] Revision snapshot record missing in installment_revisions.\n";
    exit(1);
}
echo "[PASS] Schedule revision snapshot verified (Rev #{$revRow['revision_number']}, JSON snapshot captured)\n";

// 8. Test Printable Allotment Order
$printRes = auth_curl("http://localhost/HSMS/modules/bookings/print.php?id={$bkId}");
if ($printRes['code'] !== 200 || !str_contains($printRes['body'], 'Provisional Allotment Order')) {
    echo "[FAIL] Printable Allotment Order failed to render (HTTP {$printRes['code']})\n";
    exit(1);
}
echo "[PASS] Printable Allotment Order rendered successfully with certificate layout.\n";

echo "ALL PHASE 5 BOOKING & SALES TESTS PASSED 100%!\n";
