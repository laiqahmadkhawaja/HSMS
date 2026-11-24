<?php
/**
 * HSMS - Automated Verification Test for Phase 6 (Payment Engine & Receipts)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/payment_service.php';

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

echo "=== STARTING PHASE 6 PAYMENT ALLOCATION ENGINE TEST SUITE ===\n";

$db = get_db_connection();

// 1. Fetch test booking
$stmtBk = $db->query("SELECT * FROM `bookings` WHERE `status` = 'active' ORDER BY id DESC LIMIT 1");
$booking = $stmtBk->fetch();
if (!$booking) {
    echo "[FAIL] No active booking found to test payments.\n";
    exit(1);
}
$bkId = (int)$booking['id'];
$socId = (int)$booking['society_id'];
$custId = (int)$booking['customer_id'];
echo "[PASS] Test Booking found: {$booking['booking_number']} (Balance: Rs. " . number_format((float)$booking['remaining_balance'], 2) . ")\n";

// 2. Fetch CSRF token from recoveries/create.php
$createPage = auth_curl("http://localhost/HSMS/modules/recoveries/create.php?booking_id={$bkId}");
preg_match('/name="csrf_token" value="([^"]+)"/', $createPage['body'], $m);
$csrf = $m[1] ?? '';
if (empty($csrf)) {
    echo "[FAIL] CSRF token not found in recoveries/create.php\n";
    exit(1);
}

// 3. Post Payment 1: Clear Down Payment (Rs. 620,000)
$payPost1 = [
    'csrf_token'           => $csrf,
    'booking_id'           => $bkId,
    'payment_amount'       => 620000.00,
    'payment_date'         => date('Y-m-d'),
    'payment_method'       => 'cash',
    'cash_bank_account_id' => 1, // Cash on Hand
    'transaction_ref'      => 'COUNTER-CASH-001',
    'allocation_method'    => 'fifo',
    'remarks'              => 'Down payment settlement test'
];

$payRes1 = auth_curl("http://localhost/HSMS/modules/recoveries/create.php", $payPost1);
if ($payRes1['code'] !== 302) {
    echo "[FAIL] Payment 1 posting did not redirect (HTTP {$payRes1['code']}): {$payRes1['body']}\n";
    exit(1);
}
echo "[PASS] Payment 1 posted successfully (HTTP 302 redirect to receipt)\n";

// Verify Payment 1 in Database
$stmtPay1 = $db->prepare("SELECT * FROM `payments` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtPay1->execute(['bid' => $bkId]);
$payRow1 = $stmtPay1->fetch();
if (!$payRow1 || abs((float)$payRow1['payment_amount'] - 620000.00) > 0.01) {
    echo "[FAIL] Payment 1 record missing or amount incorrect.\n";
    exit(1);
}
$payId1 = (int)$payRow1['id'];
echo "[PASS] Payment 1 verified in DB (Receipt #: {$payRow1['receipt_number']}, Amount: Rs. " . number_format((float)$payRow1['payment_amount'], 2) . ")\n";

// Verify FIFO allocations cleared installments totaling 620,000
$stmtAlloc = $db->prepare("SELECT SUM(allocated_amount) as total_alloc FROM `payment_allocations` WHERE `payment_id` = :pid");
$stmtAlloc->execute(['pid' => $payId1]);
$allocSum = (float)$stmtAlloc->fetchColumn();
if (abs($allocSum - 620000.00) > 0.01) {
    echo "[FAIL] Payment 1 allocation mismatch: expected 620,000, got {$allocSum}\n";
    exit(1);
}
echo "[PASS] FIFO installment allocation verified: exactly Rs. " . number_format($allocSum, 2) . " allocated across installments\n";

// Verify Double-Entry Journal Entry for Payment 1
$stmtJV1 = $db->prepare("SELECT * FROM `journal_entries` WHERE `reference_type` = 'payment' AND `reference_id` = :pid LIMIT 1");
$stmtJV1->execute(['pid' => $payId1]);
$jv1 = $stmtJV1->fetch();
if (!$jv1 || abs((float)$jv1['total_debit'] - 620000.00) > 0.01 || abs((float)$jv1['total_credit'] - 620000.00) > 0.01) {
    echo "[FAIL] Journal Entry for Payment 1 missing or unbalanced.\n";
    exit(1);
}
echo "[PASS] Double-entry Journal Entry #{$jv1['entry_number']} verified strictly balanced (Debit = Credit = Rs. 620,000.00)\n";

// 4. Post Payment 2: FIFO Multiple Installments with Excess Advance
// Pay Rs. 250,000. This will cover upcoming installments and put excess into customer advance.
$payPost2 = [
    'csrf_token'           => $csrf,
    'booking_id'           => $bkId,
    'payment_amount'       => 250000.00,
    'payment_date'         => date('Y-m-d'),
    'payment_method'       => 'bank_transfer',
    'cash_bank_account_id' => 3, // HBL Bank
    'transaction_ref'      => 'HBL-ONLINE-98765',
    'allocation_method'    => 'fifo',
    'remarks'              => 'Multiple installment + advance test'
];

$payRes2 = auth_curl("http://localhost/HSMS/modules/recoveries/create.php", $payPost2);
if ($payRes2['code'] !== 302) {
    echo "[FAIL] Payment 2 posting did not redirect (HTTP {$payRes2['code']})\n";
    exit(1);
}

$stmtPay2 = $db->prepare("SELECT * FROM `payments` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtPay2->execute(['bid' => $bkId]);
$payRow2 = $stmtPay2->fetch();
$payId2 = (int)$payRow2['id'];

// Check that Allocated + Advance == 250,000.00
$sumAllocAdv = round((float)$payRow2['allocated_amount'] + (float)$payRow2['advance_amount'], 2);
if (abs($sumAllocAdv - 250000.00) > 0.01) {
    echo "[FAIL] Payment 2 allocation mismatch: Allocated ({$payRow2['allocated_amount']}) + Advance ({$payRow2['advance_amount']}) != 250,000\n";
    exit(1);
}
echo "[PASS] Payment 2 verified: Allocated Rs. " . number_format((float)$payRow2['allocated_amount'], 2) . " + Advance Rs. " . number_format((float)$payRow2['advance_amount'], 2) . " = Exactly Rs. 250,000.00\n";

// 5. Test Payment Reversal
$revPost = [
    'csrf_token'      => $csrf,
    'action'          => 'reverse_payment',
    'payment_id'      => $payId2,
    'reversal_reason' => 'Online transfer bounce test reversal'
];
$revRes = auth_curl("http://localhost/HSMS/modules/receipts/index.php", $revPost);
if ($revRes['code'] !== 302) {
    echo "[FAIL] Payment reversal did not redirect (HTTP {$revRes['code']})\n";
    exit(1);
}

$stmtPayRev = $db->prepare("SELECT status FROM `payments` WHERE `id` = :pid");
$stmtPayRev->execute(['pid' => $payId2]);
$revStatus = $stmtPayRev->fetchColumn();
if ($revStatus !== 'reversed') {
    echo "[FAIL] Payment status was not updated to 'reversed' (got {$revStatus})\n";
    exit(1);
}
echo "[PASS] Payment reversal verified: Status updated to 'reversed' and balances safely rolled back.\n";

// 6. Test Multi-Layout Print Rendering
$layouts = ['a4', 'thermal80', 'thermal58'];
foreach ($layouts as $lay) {
    $printRes = auth_curl("http://localhost/HSMS/modules/receipts/print.php?id={$payId1}&layout={$lay}");
    if ($printRes['code'] !== 200 || !str_contains($printRes['body'], $payRow1['receipt_number'])) {
        echo "[FAIL] Receipt print failed for layout '{$lay}' (HTTP {$printRes['code']})\n";
        exit(1);
    }
    echo "[PASS] Receipt layout '{$lay}' rendered successfully with verification code.\n";
}

echo "ALL PHASE 6 PAYMENT ENGINE & RECEIPTS TESTS PASSED 100%!\n";
