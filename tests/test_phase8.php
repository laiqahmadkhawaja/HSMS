<?php
/**
 * HSMS - Automated Verification Test for Phase 8 (Transfers & Cancellations)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/booking_service.php';
require_once __DIR__ . '/../services/payment_service.php';
require_once __DIR__ . '/../services/transfer_service.php';
require_once __DIR__ . '/../services/cancellation_service.php';

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

echo "=== STARTING PHASE 8 TRANSFERS & CANCELLATIONS TEST SUITE ===\n";

$db = get_db_connection();

// 1. Fetch active booking for transfer test
$stmtBk = $db->query("SELECT * FROM `bookings` WHERE `status` = 'active' ORDER BY id DESC LIMIT 1");
$booking = $stmtBk->fetch();
if (!$booking) {
    echo "[FAIL] No active booking found for transfer test.\n";
    exit(1);
}
$bkId = (int)$booking['id'];
$socId = (int)$booking['society_id'];
$sellerId = (int)$booking['customer_id'];
$remBal = (float)$booking['remaining_balance'];

// Identify a distinct buyer
$stmtBuyer = $db->prepare("SELECT id, full_name FROM `customers` WHERE `id` != :sid AND `status` = 'active' LIMIT 1");
$stmtBuyer->execute(['sid' => $sellerId]);
$buyer = $stmtBuyer->fetch();
if (!$buyer) {
    $stmtInsBuyer = $db->prepare("
        INSERT INTO `customers` (`customer_id_code`, `full_name`, `father_husband_name`, `cnic_passport`, `mobile`, `email`, `city`, `residential_address`, `status`)
        VALUES ('CUST-BUYER-01', 'Haji Abdul Rehman', 'Muhammad Din', '35202-9988776-1', '+92 321 9876543', 'buyer@test.local', 'Lahore', 'Model Town C-Block', 'active')
    ");
    $stmtInsBuyer->execute();
    $buyerId = (int)$db->lastInsertId();
    $buyer = ['id' => $buyerId, 'full_name' => 'Haji Abdul Rehman'];
}
$buyerId = (int)$buyer['id'];
echo "[PASS] Transfer Participants: Seller ID {$sellerId} -> Buyer ID {$buyerId} ({$buyer['full_name']})\n";

// 2. Fetch CSRF token from transfers/create.php
$createTrfPage = auth_curl("http://localhost/HSMS/modules/transfers/create.php?booking_id={$bkId}");
if ($createTrfPage['code'] !== 200) {
    echo "[FAIL] transfers/create.php failed to load (HTTP {$createTrfPage['code']})\n";
    exit(1);
}
preg_match('/name="csrf_token" value="([^"]+)"/', $createTrfPage['body'], $m);
$csrf = $m[1] ?? '';

// 3. Post Plot Transfer
$trfPost = [
    'csrf_token'           => $csrf,
    'booking_id'           => $bkId,
    'to_customer_id'       => $buyerId,
    'transfer_fee'         => 50000.00,
    'transfer_fee_status'  => 'paid',
    'cash_bank_account_id' => 1, // Cash on Hand
    'transfer_date'        => date('Y-m-d'),
    'remarks'              => 'Phase 8 automated test transfer execution'
];
$trfRes = auth_curl("http://localhost/HSMS/modules/transfers/create.php", $trfPost);
if ($trfRes['code'] !== 302) {
    echo "[FAIL] Transfer posting did not redirect (HTTP {$trfRes['code']})\n";
    exit(1);
}
echo "[PASS] Transfer posted successfully (HTTP 302 redirect)\n";

// Verify Transfer in Database
$stmtTrf = $db->prepare("SELECT * FROM `plot_transfers` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtTrf->execute(['bid' => $bkId]);
$trfRow = $stmtTrf->fetch();
if (!$trfRow || (int)$trfRow['to_customer_id'] !== $buyerId || abs((float)$trfRow['transfer_fee'] - 50000.00) > 0.01) {
    echo "[FAIL] Transfer record missing or incorrect in plot_transfers table.\n";
    exit(1);
}
$trfId = (int)$trfRow['id'];
echo "[PASS] Transfer verified in DB: Ref #{$trfRow['transfer_number']}, Balance Taken Over: Rs. " . number_format((float)$trfRow['pending_balance_transferred'], 2) . "\n";

// Verify Booking Customer reassigned
$stmtBkCheck = $db->prepare("SELECT customer_id FROM `bookings` WHERE `id` = :bid");
$stmtBkCheck->execute(['bid' => $bkId]);
if ((int)$stmtBkCheck->fetchColumn() !== $buyerId) {
    echo "[FAIL] Booking customer_id was not updated to buyer ID {$buyerId}.\n";
    exit(1);
}
echo "[PASS] Booking contract customer_id successfully updated to new buyer ID {$buyerId}.\n";

// Verify Seller and Buyer Ledgers
$stmtSellerL = $db->prepare("SELECT * FROM `customer_ledger` WHERE `customer_id` = :cid AND `transaction_type` = 'transfer_credit' AND `booking_id` = :bid");
$stmtSellerL->execute(['cid' => $sellerId, 'bid' => $bkId]);
$sellerLedger = $stmtSellerL->fetch();
if (!$sellerLedger || (float)$sellerLedger['running_balance'] != 0.00) {
    echo "[FAIL] Seller transfer_credit ledger entry missing or balance not zeroed out.\n";
    exit(1);
}
echo "[PASS] Seller ledger closed out: Running balance is Rs. 0.00.\n";

$stmtBuyerL = $db->prepare("SELECT * FROM `customer_ledger` WHERE `customer_id` = :cid AND `transaction_type` = 'transfer_debit' AND `booking_id` = :bid");
$stmtBuyerL->execute(['cid' => $buyerId, 'bid' => $bkId]);
$buyerLedger = $stmtBuyerL->fetch();
if (!$buyerLedger || abs((float)$buyerLedger['debit'] - $remBal) > 0.01) {
    echo "[FAIL] Buyer transfer_debit ledger entry missing or debit balance incorrect.\n";
    exit(1);
}
echo "[PASS] Buyer ledger opened: Debit balance of Rs. " . number_format((float)$buyerLedger['debit'], 2) . " assumed.\n";

// Verify Transfer Fee Journal Entry
$stmtJV = $db->prepare("SELECT * FROM `journal_entries` WHERE `reference_type` = 'transfer' AND `reference_id` = :tid");
$stmtJV->execute(['tid' => $trfId]);
$jvRow = $stmtJV->fetch();
if (!$jvRow || abs((float)$jvRow['total_debit'] - 50000.00) > 0.01 || abs((float)$jvRow['total_credit'] - 50000.00) > 0.01) {
    echo "[FAIL] Transfer fee double-entry Journal Entry missing or unbalanced.\n";
    exit(1);
}
echo "[PASS] Double-entry JV #{$jvRow['entry_number']} verified strictly balanced (Debit = Credit = Rs. 50,000.00)\n";

// Verify Printable Transfer Deed Layout
$printTrfRes = auth_curl("http://localhost/HSMS/modules/transfers/print.php?id={$trfId}");
if ($printTrfRes['code'] !== 200 || !str_contains($printTrfRes['body'], $trfRow['transfer_number'])) {
    echo "[FAIL] Transfer Deed print layout failed to render (HTTP {$printTrfRes['code']}): " . substr($printTrfRes['body'], 0, 500) . "\n";
    exit(1);
}
echo "[PASS] Printable Deed of Allotment Transfer rendered successfully (HTTP 200).\n";

// 4. Test Plot Cancellation & Inventory Release
// Create a separate test booking on an available plot to verify cancellation
$stmtAvailPlot = $db->query("SELECT id, plot_number FROM `plots` WHERE `status` = 'available' LIMIT 1");
$availPlot = $stmtAvailPlot->fetch();
if (!$availPlot) {
    echo "[FAIL] No available plot found to test cancellation.\n";
    exit(1);
}
$cancelPlotId = (int)$availPlot['id'];

$cancelBkId = BookingService::createBooking(
    $socId,
    $cancelPlotId,
    $sellerId,
    null,
    date('Y-m-d'),
    2500000.00,
    0.00,
    0.00,
    0.00,
    500000.00,
    12,
    'monthly',
    10,
    null,
    1,
    'Temporary booking for cancellation testing',
    []
);

// Post down payment so booking has paid money to test deduction
PaymentService::postPayment(
    $socId,
    $cancelBkId,
    $sellerId,
    500000.00,
    date('Y-m-d'),
    'cash',
    1,
    'CANC-TEST-DP',
    'fifo',
    null,
    null,
    'Down payment on cancellation test booking',
    'cnl-ikey-' . time(),
    1
);
echo "[PASS] Created test booking #{$cancelBkId} on Plot #{$availPlot['plot_number']} with Rs. 500,000 paid.\n";

// Get CSRF from cancellations/create.php
$createCnlPage = auth_curl("http://localhost/HSMS/modules/cancellations/create.php?booking_id={$cancelBkId}");
preg_match('/name="csrf_token" value="([^"]+)"/', $createCnlPage['body'], $mCnl);
$cnlCsrf = $mCnl[1] ?? '';

// Post Cancellation: 10% deduction (Rs. 50,000), Refund payable = Rs. 450,000
$cnlPost = [
    'csrf_token'        => $cnlCsrf,
    'booking_id'        => $cancelBkId,
    'deduction_amount'  => 50000.00,
    'cancellation_date' => date('Y-m-d'),
    'reason'            => 'Customer submitted voluntary surrender application due to financial relocation'
];
$cnlRes = auth_curl("http://localhost/HSMS/modules/cancellations/create.php", $cnlPost);
if ($cnlRes['code'] !== 302) {
    echo "[FAIL] Cancellation posting did not redirect (HTTP {$cnlRes['code']})\n";
    exit(1);
}
echo "[PASS] Cancellation posted successfully (HTTP 302 redirect)\n";

// Verify Cancellation Record in DB
$stmtCnl = $db->prepare("SELECT * FROM `cancellations` WHERE `booking_id` = :bid ORDER BY id DESC LIMIT 1");
$stmtCnl->execute(['bid' => $cancelBkId]);
$cnlRow = $stmtCnl->fetch();
if (!$cnlRow || abs((float)$cnlRow['deduction_amount'] - 50000.00) > 0.01 || abs((float)$cnlRow['refund_amount_payable'] - 450000.00) > 0.01) {
    echo "[FAIL] Cancellation record missing or financial figures incorrect.\n";
    exit(1);
}
$cnlId = (int)$cnlRow['id'];
echo "[PASS] Cancellation verified: Order #{$cnlRow['cancellation_number']}, Retained: Rs. 50,000.00, Refund Payable: Rs. 450,000.00\n";

// CRITICAL VERIFICATION: Booking status is 'cancelled'
$stmtBkStat = $db->prepare("SELECT status FROM `bookings` WHERE `id` = :bid");
$stmtBkStat->execute(['bid' => $cancelBkId]);
if ($stmtBkStat->fetchColumn() !== 'cancelled') {
    echo "[FAIL] Booking status was not updated to 'cancelled'.\n";
    exit(1);
}
echo "[PASS] Booking status successfully updated to 'cancelled'.\n";

// CRITICAL VERIFICATION: Plot status reverted from 'booked' to 'available'
$stmtPlotStat = $db->prepare("SELECT status FROM `plots` WHERE `id` = :pid");
$stmtPlotStat->execute(['pid' => $cancelPlotId]);
$plotStatus = $stmtPlotStat->fetchColumn();
if ($plotStatus !== 'available') {
    echo "[FAIL] Plot status was NOT reverted to 'available' (got '{$plotStatus}').\n";
    exit(1);
}
echo "[PASS] INVENTORY RESTORATION CONFIRMED: Plot #{$availPlot['plot_number']} released back to 'available' status!\n";

// Verify Customer Ledger closed
$stmtCnlLedger = $db->prepare("SELECT * FROM `customer_ledger` WHERE `booking_id` = :bid AND `transaction_type` = 'cancellation_credit'");
$stmtCnlLedger->execute(['bid' => $cancelBkId]);
$cnlLedgerRow = $stmtCnlLedger->fetch();
if (!$cnlLedgerRow || (float)$cnlLedgerRow['running_balance'] != 0.00) {
    echo "[FAIL] Customer ledger cancellation_credit entry missing or balance not zeroed out.\n";
    exit(1);
}
echo "[PASS] Customer ledger closed: Contract balance zeroed out.\n";

// Verify Printable Cancellation Order
$printCnlRes = auth_curl("http://localhost/HSMS/modules/cancellations/print.php?id={$cnlId}");
if ($printCnlRes['code'] !== 200 || !str_contains($printCnlRes['body'], $cnlRow['cancellation_number'])) {
    echo "[FAIL] Cancellation print layout failed to render (HTTP {$printCnlRes['code']})\n";
    exit(1);
}
echo "[PASS] Printable Order of Allotment Cancellation rendered successfully (HTTP 200).\n";

echo "ALL PHASE 8 TRANSFERS & CANCELLATIONS TESTS PASSED 100%!\n";
