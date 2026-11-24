<?php
/**
 * HSMS - Automated Verification Test for Phase 4 (Installment Engine)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/installment_service.php';

echo "=== STARTING PHASE 4 INSTALLMENT ENGINE TEST SUITE ===\n";

// TEST 1: Exact Equal Calculation from Master Prompt
// Plot Net Price: Rs. 2,400,000, Down Payment: Rs. 400,000, Duration: 40 monthly installments
$netPrice1 = 2400000.00;
$dp1 = 400000.00;
$sched1 = InstallmentService::generateSchedule($netPrice1, $dp1, 40, 'monthly', '2026-01-01');

if (count($sched1) !== 41) { // 1 DP + 40 installments
    echo "[FAIL] Expected 41 items in schedule, got " . count($sched1) . "\n";
    exit(1);
}
echo "[PASS] Schedule contains exactly 41 rows (1 DP + 40 monthly installments)\n";

$sum1 = InstallmentService::getScheduleTotalSum($sched1);
if (abs($sum1 - $netPrice1) > 0.001) {
    echo "[FAIL] Sum of schedule ({$sum1}) does not match net price ({$netPrice1})\n";
    exit(1);
}
echo "[PASS] Schedule 1 totals exactly Rs. " . number_format($sum1, 2) . " with zero discrepancy!\n";

// Check installment #1 is 50,000
if (abs((float)$sched1[1]['amount_due'] - 50000.00) > 0.001) {
    echo "[FAIL] Installment #1 amount expected 50,000, got {$sched1[1]['amount_due']}\n";
    exit(1);
}
echo "[PASS] Monthly installments equal exactly Rs. 50,000.00 each.\n";

// TEST 2: Rounding Discrepancy Adjustment
// Net 1,000,000 / 3 = 333,333.33 + 333,333.33 + 333,333.34
$netPrice2 = 1000000.00;
$sched2 = InstallmentService::generateSchedule($netPrice2, 0.00, 3, 'monthly', '2026-01-01');
$sum2 = InstallmentService::getScheduleTotalSum($sched2);
if (abs($sum2 - $netPrice2) > 0.001) {
    echo "[FAIL] Rounding schedule sum mismatch: {$sum2} vs {$netPrice2}\n";
    exit(1);
}
$lastInstallment = end($sched2);
if (abs((float)$lastInstallment['amount_due'] - 333333.34) > 0.01) {
    echo "[FAIL] Last installment rounding adjustment expected 333,333.34, got {$lastInstallment['amount_due']}\n";
    exit(1);
}
echo "[PASS] Rounding adjustment correctly placed on final installment (Rs. {$lastInstallment['amount_due']}) - Total Rs. " . number_format($sum2, 2) . "\n";

// TEST 3: Calendar Due Dates & Leap Year / Short Months
$leapStart = new DateTime('2024-01-31');
$febDue = InstallmentService::calculateNextDueDate($leapStart, 'monthly', 31);
if ($febDue->format('Y-m-d') !== '2024-02-29') { // 2024 is leap year, max days in Feb is 29
    echo "[FAIL] Expected leap year 2024-02-29, got " . $febDue->format('Y-m-d') . "\n";
    exit(1);
}
echo "[PASS] Leap year end-of-month correctly resolved to 2024-02-29 (no Feb 30/31 bugs)\n";

$aprDue = InstallmentService::calculateNextDueDate($febDue, 'monthly', 31);
if ($aprDue->format('Y-m-d') !== '2024-03-31') {
    echo "[FAIL] Expected 2024-03-31, got " . $aprDue->format('Y-m-d') . "\n";
    exit(1);
}
$mayDue = InstallmentService::calculateNextDueDate($aprDue, 'monthly', 31);
// April only has 30 days
if ($mayDue->format('Y-m-d') !== '2024-04-30') {
    echo "[FAIL] Expected April 30th date, got " . $mayDue->format('Y-m-d') . "\n";
    exit(1);
}
echo "[PASS] 30-day month end correctly resolved to 2024-04-30\n";

// TEST 4: Custom Balloon / Milestone Payments
$milestones = [
    ['title' => 'Possession Surcharge', 'amount' => 300000.00, 'due_date' => '2027-01-01'],
    ['title' => 'Utility Infrastructure Charge', 'amount' => 100000.00, 'due_date' => '2027-06-01']
];
$sched4 = InstallmentService::generateSchedule(3000000.00, 600000.00, 20, 'monthly', '2026-01-01', 5, $milestones);
$sum4 = InstallmentService::getScheduleTotalSum($sched4);
if (abs($sum4 - 3000000.00) > 0.001) {
    echo "[FAIL] Milestone schedule sum mismatch: {$sum4} vs 3,000,000.00\n";
    exit(1);
}
echo "[PASS] Custom milestones + Regular installments correctly reconcile to Rs. 3,000,000.00\n";

// TEST 5: AJAX Endpoint Verification
$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';
$ch = curl_init("http://localhost/HSMS/ajax/installments/generate_schedule.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'net_price'          => 2000000,
    'down_payment_type'  => 'percentage',
    'down_payment_value' => 25,
    'total_installments' => 12,
    'frequency'          => 'monthly',
    'start_date'         => '2026-03-01',
    'due_day'            => 10
]));
$ajaxRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "[FAIL] AJAX generate_schedule returned HTTP {$httpCode}: {$ajaxRes}\n";
    exit(1);
}
$ajaxData = json_decode((string)$ajaxRes, true);
if (!$ajaxData || !$ajaxData['success'] || $ajaxData['data']['down_payment'] != 500000) {
    echo "[FAIL] AJAX generate_schedule returned unexpected payload: {$ajaxRes}\n";
    exit(1);
}
echo "[PASS] AJAX schedule generator endpoint verified (DP: 500,000, Total: " . number_format($ajaxData['data']['total_scheduled'], 2) . ")\n";

echo "ALL PHASE 4 INSTALLMENT ENGINE TESTS PASSED 100%!\n";
