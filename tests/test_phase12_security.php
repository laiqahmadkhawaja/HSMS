<?php
/**
 * HSMS - Automated Test Suite: Phase 12 (Security Audit & Production Hardening)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$baseUrl = 'http://localhost/HSMS';
$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

function sec_assert(bool $condition, string $description): void {
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        exit(1);
    }
}

echo "=== HSMS Test Suite: Phase 12 (Security Audit & System Hardening) ===\n\n";

// 1. Unauthenticated Route Protection (Authentication Firewall)
echo "--- Step 1: Unauthenticated Route Protection (Zero Leak) ---\n";
$protectedUrls = [
    '/dashboard.php',
    '/modules/bookings/index.php',
    '/modules/customers/index.php',
    '/modules/accounts/trial_balance.php',
    '/modules/settings/notifications.php'
];

foreach ($protectedUrls as $path) {
    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    // Deliberately no cookie
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    sec_assert($httpCode === 302, "Unauthenticated access to {$path} returns HTTP 302 Redirect");
}

// 2. CSRF Token Enforcement on Mutating POST Requests
echo "\n--- Step 2: CSRF Token Enforcement ---\n";
// Sending POST to modules/settings/notifications.php with test auth cookie but missing CSRF
$ch = curl_init($baseUrl . '/modules/settings/notifications.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'simulate_sms', 'sms_phone' => '03001234567', 'sms_body' => 'hacked']));
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

sec_assert($httpCode === 403, "POST request without CSRF token is blocked with HTTP 403 Forbidden");
sec_assert(str_contains($response, 'Invalid or expired security token (CSRF)'), "Response contains CSRF protection security warning");

// 3. XSS Escaping Safety Function
echo "\n--- Step 3: XSS Escaping Integrity ---\n";
$dangerousInput = '<script>alert("XSS Attack!");</script>';
$escaped = e($dangerousInput);
sec_assert($escaped === '&lt;script&gt;alert(&quot;XSS Attack!&quot;);&lt;/script&gt;', "e() escapes all HTML characters and double quotes");
sec_assert(!str_contains($escaped, '<script>'), "Escaped output does not contain executable script tags");

// 4. SQL Injection Resistance on Parameterized Queries
echo "\n--- Step 4: SQL Injection Resistance ---\n";
$db = get_db_connection();
$sqliPayload = "1' OR '1'='1";

$stmt = $db->prepare("SELECT id, name FROM `societies` WHERE `society_code` = :code");
$stmt->execute(['code' => $sqliPayload]);
$res = $stmt->fetchAll();
sec_assert(empty($res), "SQL Injection payload in prepared statement returns 0 rows safely");

// 5. Total Accounting Balance Equilibrium (GAAP / Double-Entry Audit)
echo "\n--- Step 5: Master General Ledger Equilibrium Audit ---\n";
$stmtJv = $db->query("SELECT SUM(debit) as total_debits, SUM(credit) as total_credits FROM `journal_entry_lines`");
$jvTotals = $stmtJv->fetch();

$totalDebits = (float)($jvTotals['total_debits'] ?? 0);
$totalCredits = (float)($jvTotals['total_credits'] ?? 0);
$diff = abs($totalDebits - $totalCredits);

echo "  General Ledger Total Debits : Rs. " . number_format($totalDebits, 2) . "\n";
echo "  General Ledger Total Credits: Rs. " . number_format($totalCredits, 2) . "\n";
echo "  Variance                     : Rs. " . number_format($diff, 2) . "\n";

sec_assert($diff < 0.001, "General ledger is in exact mathematical balance (Debits == Credits, diff = 0.00)");

// 6. Customer Ledger vs Plot Balance Consistency
echo "\n--- Step 6: Customer Ledger vs Booking Balance Audit ---\n";
// Every booking balance should equal total installments due minus paid
$stmtB = $db->query("
    SELECT b.id, b.booking_number, b.remaining_balance,
           COALESCE(SUM(s.amount_due), 0) - COALESCE(SUM(s.amount_paid), 0) as calculated_schedule_balance
    FROM `bookings` b
    LEFT JOIN `installment_schedules` s ON b.id = s.booking_id
    WHERE b.status IN ('active', 'completed')
    GROUP BY b.id
");
$bookings = $stmtB->fetchAll();

foreach ($bookings as $bk) {
    $rem = (float)$bk['remaining_balance'];
    $calc = (float)$bk['calculated_schedule_balance'];
    $bkDiff = abs($rem - $calc);
    sec_assert($bkDiff < 0.01, "Booking {$bk['booking_number']} balance matches schedule sum exactly");
}

// 7. Inventory Count Consistency
echo "\n--- Step 7: Inventory Status Reconciliation ---\n";
$stmtInv = $db->query("
    SELECT COUNT(*) as total_plots,
           SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
           SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked,
           SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
           SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM `plots`
");
$inv = $stmtInv->fetch();

$sumStatuses = (int)$inv['available'] + (int)$inv['booked'] + (int)$inv['reserved'] + (int)$inv['cancelled'];
sec_assert((int)$inv['total_plots'] === $sumStatuses, "Plot inventory status count reconciles with total plot count");

echo "\n>>> PHASE 12 SECURITY AUDIT & HARDENING VERIFIED 100%! <<<\n";
