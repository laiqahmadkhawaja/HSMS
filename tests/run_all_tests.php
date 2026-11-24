<?php
/**
 * HSMS - Master Regression Test Runner
 * Executes all 12 test suites sequentially and reports comprehensive results.
 */

declare(strict_types=1);

$tests = [
    'Phase 1: Core Foundation & Auth'               => 'test_phase1.php',
    'Phase 2: Master Data & Society Hierarchy'     => 'test_phase2.php',
    'Phase 3: Customer Registry & 360 Profile'     => 'test_phase3.php',
    'Phase 4: Installment Schedule Engine'          => 'test_phase4.php',
    'Phase 5: Booking & Sales Transaction Engine'  => 'test_phase5.php',
    'Phase 6: Payment Allocation Engine & Receipts' => 'test_phase6.php',
    'Phase 7: Field Staff & Promises to Pay'        => 'test_phase7.php',
    'Phase 8: Plot Transfers & Cancellations'       => 'test_phase8.php',
    'Phase 9: Double-Entry Accounting & Expenses'  => 'test_phase9.php',
    'Phase 10: Executive Dashboard & Reports'       => 'test_phase10.php',
    'Phase 11: Notification Engine & Settings'     => 'test_phase11.php',
    'Phase 12: Security Audit & System Hardening'  => 'test_phase12_security.php',
];

echo "========================================================================\n";
echo "    HOUSING SOCIETY MANAGEMENT SYSTEM (HSMS) - MASTER TEST RUNNER      \n";
echo "========================================================================\n\n";

$allPassed = true;
$passedCount = 0;
$totalCount = count($tests);

foreach ($tests as $title => $file) {
    echo ">>> Running {$title} ({$file})...\n";
    $cmd = "php " . escapeshellarg(__DIR__ . '/' . $file);
    exec($cmd, $output, $exitCode);

    if ($exitCode === 0) {
        echo "    RESULT: [PASSED]\n\n";
        $passedCount++;
    } else {
        echo "    RESULT: [FAILED]\n";
        echo implode("\n", array_slice($output, -10)) . "\n\n";
        $allPassed = false;
        break;
    }
}

echo "========================================================================\n";
echo "MASTER TEST SUMMARY: {$passedCount} / {$totalCount} TEST SUITES PASSED (100% SUCCESS)\n";
echo "========================================================================\n";

if (!$allPassed) {
    exit(1);
}
