<?php
/**
 * HSMS - Automated Verification Test for Phase 10 (Dashboard & Operational Reports)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

function auth_curl(string $url): array {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $raw = (string)curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

echo "=== STARTING PHASE 10 DASHBOARD & OPERATIONAL REPORTS TEST SUITE ===\n";

// 1. Executive Dashboard Test
$dash = auth_curl("http://localhost/HSMS/dashboard.php");
if ($dash['code'] !== 200 || !str_contains($dash['body'], 'Executive Dashboard') || !str_contains($dash['body'], 'inventoryChart')) {
    echo "[FAIL] Dashboard failed to render with operational metrics or Chart.js (HTTP {$dash['code']})\n";
    exit(1);
}
echo "[PASS] Executive Dashboard verified: HTTP 200 with dynamic metrics & Chart.js canvas.\n";

// 2. Reports Hub Test
$hub = auth_curl("http://localhost/HSMS/modules/reports/index.php");
if ($hub['code'] !== 200 || !str_contains($hub['body'], 'Executive Reports Center')) {
    echo "[FAIL] Reports Hub failed to render (HTTP {$hub['code']})\n";
    exit(1);
}
echo "[PASS] Executive Reports Center verified: HTTP 200 with all reporting module cards.\n";

// 3. Sales Register Report & CSV Export
$sales = auth_curl("http://localhost/HSMS/modules/reports/sales_register.php");
if ($sales['code'] !== 200 || !str_contains($sales['body'], 'Plot Sales Register')) {
    echo "[FAIL] Sales Register page failed to render (HTTP {$sales['code']})\n";
    exit(1);
}
$salesCsv = auth_curl("http://localhost/HSMS/modules/reports/sales_register.php?export=csv");
if ($salesCsv['code'] !== 200 || !str_contains($salesCsv['body'], 'Sales Register Report') || !str_contains($salesCsv['body'], 'TOTALS')) {
    echo "[FAIL] Sales Register CSV export failed (HTTP {$salesCsv['code']})\n";
    exit(1);
}
echo "[PASS] Sales Register Report & CSV export verified successfully.\n";

// 4. Plot Inventory Report & CSV Export
$inv = auth_curl("http://localhost/HSMS/modules/reports/inventory.php");
if ($inv['code'] !== 200 || !str_contains($inv['body'], 'Plot Inventory & Occupancy Report')) {
    echo "[FAIL] Plot Inventory page failed to render (HTTP {$inv['code']})\n";
    exit(1);
}
$invCsv = auth_curl("http://localhost/HSMS/modules/reports/inventory.php?export=csv");
if ($invCsv['code'] !== 200 || !str_contains($invCsv['body'], 'Plot Inventory & Occupancy Report') || !str_contains($invCsv['body'], 'TOTAL INVENTORY')) {
    echo "[FAIL] Plot Inventory CSV export failed (HTTP {$invCsv['code']})\n";
    exit(1);
}
echo "[PASS] Plot Inventory & Occupancy Report & CSV export verified successfully.\n";

// 5. Overdue Aging Analysis Report & CSV Export
$aging = auth_curl("http://localhost/HSMS/modules/reports/aging.php");
if ($aging['code'] !== 200 || !str_contains($aging['body'], 'Overdue Aging Analysis Report') || !str_contains($aging['body'], '180+ Days')) {
    echo "[FAIL] Overdue Aging Analysis page failed to render (HTTP {$aging['code']})\n";
    exit(1);
}
$agingCsv = auth_curl("http://localhost/HSMS/modules/reports/aging.php?export=csv");
if ($agingCsv['code'] !== 200 || !str_contains($agingCsv['body'], 'Overdue Aging Analysis Report')) {
    echo "[FAIL] Overdue Aging Analysis CSV export failed (HTTP {$agingCsv['code']})\n";
    exit(1);
}
echo "[PASS] Overdue Aging Analysis Report & CSV export verified successfully.\n";

// 6. Persistent Defaulters Report & CSV Export
$def = auth_curl("http://localhost/HSMS/modules/reports/defaulters.php");
if ($def['code'] !== 200 || !str_contains($def['body'], 'Persistent Defaulters Register')) {
    echo "[FAIL] Defaulters page failed to render (HTTP {$def['code']})\n";
    exit(1);
}
$defCsv = auth_curl("http://localhost/HSMS/modules/reports/defaulters.php?export=csv");
if ($defCsv['code'] !== 200 || !str_contains($defCsv['body'], 'Defaulters & Arrears Register')) {
    echo "[FAIL] Defaulters CSV export failed (HTTP {$defCsv['code']})\n";
    exit(1);
}
echo "[PASS] Persistent Defaulters Report & CSV export verified successfully.\n";

echo "ALL PHASE 10 EXECUTIVE DASHBOARD & OPERATIONAL REPORTS TESTS PASSED 100%!\n";
