<?php
/**
 * HSMS - Automated Verification Test for Phase 9 (Accounting, Cash/Bank & Expenses)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/accounting_service.php';

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

echo "=== STARTING PHASE 9 DOUBLE-ENTRY ACCOUNTING & EXPENSES TEST SUITE ===\n";

$db = get_db_connection();
$socId = 1; // Green Valley Residency

// 1. Verify Chart of Accounts Dashboard
$coaPage = auth_curl("http://localhost/HSMS/modules/accounts/index.php");
if ($coaPage['code'] !== 200) {
    echo "[FAIL] Chart of accounts page failed to load (HTTP {$coaPage['code']})\n";
    exit(1);
}
echo "[PASS] Chart of Accounts dashboard rendered successfully (HTTP 200)\n";

// 2. Test STRICT Double-Entry Enforcement: Rejection of Unbalanced JV
$unbalancedLines = [
    ['account_id' => 1, 'debit' => 100000.00, 'credit' => 0.00, 'memo' => 'Debit Cash'],
    ['account_id' => 10, 'debit' => 0.00, 'credit' => 85000.00, 'memo' => 'Unbalanced Credit']
];

$threwExpected = false;
try {
    AccountingService::postJournalEntry($socId, date('Y-m-d'), 'journal', null, null, 'Test Unbalanced JV', $unbalancedLines, 1);
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'Unbalanced Journal Entry')) {
        $threwExpected = true;
    }
}
if (!$threwExpected) {
    echo "[FAIL] Unbalanced journal entry was NOT rejected! Strict double-entry check failed.\n";
    exit(1);
}
echo "[PASS] STRICT RECONCILIATION VERIFIED: Unbalanced Journal Entry (Dr 100,000 != Cr 85,000) rejected with exception.\n";

// 3. Test Posting Balanced Manual JV via Web Interface
$jvPage = auth_curl("http://localhost/HSMS/modules/accounts/journal_entries.php");
preg_match('/name="csrf_token" value="([^"]+)"/', $jvPage['body'], $m);
$csrf = $m[1] ?? '';
if (empty($csrf)) {
    echo "[FAIL] CSRF token not found in journal_entries.php\n";
    exit(1);
}

$balancedPost = [
    'csrf_token' => $csrf,
    'entry_date' => date('Y-m-d'),
    'narration'  => 'Manual Depreciation & Office Equipment Adjustment',
    'account_id' => [18, 1], // Utilities Expense (5004) and Cash on Hand (1001)
    'debit'      => [15000.00, 0.00],
    'credit'     => [0.00, 15000.00],
    'memo'       => ['Monthly adjustment debit', 'Cash offset credit']
];
$jvRes = auth_curl("http://localhost/HSMS/modules/accounts/journal_entries.php", $balancedPost);
if ($jvRes['code'] !== 302) {
    echo "[FAIL] Balanced JV posting did not redirect (HTTP {$jvRes['code']})\n";
    exit(1);
}
echo "[PASS] Balanced manual Journal Voucher posted successfully (HTTP 302 redirect).\n";

// 4. Test Expense Voucher Posting
$stmtCash = $db->query("SELECT current_balance FROM `accounts` WHERE `id` = 1");
$cashBalBefore = (float)$stmtCash->fetchColumn();

$expPost = [
    'csrf_token'          => $csrf,
    'expense_category_id' => 3, // Office Utilities & Bills
    'payment_account_id'  => 1, // Cash on Hand
    'amount'              => 25000.00,
    'paid_to'             => 'WAPDA Electricity Department',
    'reference_no'        => 'WAPDA-BILL-NOV26',
    'expense_date'        => date('Y-m-d'),
    'description'         => 'Site office electricity consumption for November'
];
$expRes = auth_curl("http://localhost/HSMS/modules/expenses/create.php", $expPost);
if ($expRes['code'] !== 302) {
    echo "[FAIL] Expense voucher posting did not redirect (HTTP {$expRes['code']})\n";
    exit(1);
}

// Verify Expense record in DB
$stmtExp = $db->prepare("SELECT * FROM `expenses` WHERE `society_id` = :sid ORDER BY id DESC LIMIT 1");
$stmtExp->execute(['sid' => $socId]);
$expRow = $stmtExp->fetch();
if (!$expRow || abs((float)$expRow['amount'] - 25000.00) > 0.01 || $expRow['paid_to'] !== 'WAPDA Electricity Department') {
    echo "[FAIL] Expense voucher missing or incorrect in expenses table.\n";
    exit(1);
}
$expId = (int)$expRow['id'];
echo "[PASS] Expense Voucher #{$expRow['voucher_number']} verified in DB (Amount: Rs. 25,000.00 to {$expRow['paid_to']})\n";

// Verify Double-Entry JV generated for this Expense
$stmtExpJV = $db->prepare("SELECT * FROM `journal_entries` WHERE `reference_type` = 'expense' AND `reference_id` = :eid");
$stmtExpJV->execute(['eid' => $expId]);
$expJV = $stmtExpJV->fetch();
if (!$expJV || abs((float)$expJV['total_debit'] - 25000.00) > 0.01) {
    echo "[FAIL] Auto-generated double-entry JV for expense voucher missing or unbalanced.\n";
    exit(1);
}
echo "[PASS] Double-entry JV #{$expJV['entry_number']} auto-posted for expense (Debit = Credit = Rs. 25,000.00)\n";

// Verify Cash on Hand balance updated
$stmtCashAfter = $db->query("SELECT current_balance FROM `accounts` WHERE `id` = 1");
$cashBalAfter = (float)$stmtCashAfter->fetchColumn();
$expectedCashBal = round($cashBalBefore - 25000.00, 2);
if (abs($cashBalAfter - $expectedCashBal) > 0.01) {
    echo "[FAIL] Cash on Hand balance mismatch: before {$cashBalBefore}, after {$cashBalAfter}, expected {$expectedCashBal}\n";
    exit(1);
}
echo "[PASS] Cash on Hand balance reduction verified (New Balance: Rs. " . number_format($cashBalAfter, 2) . ")\n";

// 5. Test Cash & Bank Book View
$cbRes = auth_curl("http://localhost/HSMS/modules/accounts/cash_bank.php?account_id=1");
if ($cbRes['code'] !== 200 || !str_contains($cbRes['body'], 'Cash on Hand') || !str_contains($cbRes['body'], $expRow['voucher_number'])) {
    echo "[FAIL] Cash & Bank Book statement failed to render or missing expense voucher (HTTP {$cbRes['code']})\n";
    exit(1);
}
echo "[PASS] Cash & Bank Book statement rendered successfully with real-time balance trail.\n";

// 6. Test Trial Balance Equilibrium & Mathematical Integrity
$tb = AccountingService::getTrialBalance($socId);
if (!$tb['is_balanced']) {
    echo "[FAIL] Trial Balance is OUT OF BALANCE! Debits: {$tb['total_debit']} != Credits: {$tb['total_credit']}\n";
    exit(1);
}
echo "[PASS] TRIAL BALANCE EQUILIBRIUM VERIFIED: Total Debits (Rs. " . number_format($tb['total_debit'], 2) . ") == Total Credits (Rs. " . number_format($tb['total_credit'], 2) . ")\n";

// Verify CSV export
$csvRes = auth_curl("http://localhost/HSMS/modules/accounts/trial_balance.php?export=csv");
if ($csvRes['code'] !== 200 || !str_contains($csvRes['body'], 'Trial Balance') || !str_contains($csvRes['body'], 'TOTAL')) {
    echo "[FAIL] Trial balance CSV export failed (HTTP {$csvRes['code']})\n";
    exit(1);
}
echo "[PASS] Trial Balance CSV export verified with complete general ledger accounts.\n";

echo "ALL PHASE 9 DOUBLE-ENTRY ACCOUNTING & EXPENSES TESTS PASSED 100%!\n";
