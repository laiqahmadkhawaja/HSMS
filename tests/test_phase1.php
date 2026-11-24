<?php
/**
 * HSMS - Automated Verification Test for Phase 1
 */

declare(strict_types=1);

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

// 1. GET login page and extract CSRF
$ch = curl_init('http://localhost/HSMS/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode !== 200) {
    echo "[FAIL] login.php HTTP Status: {$httpCode}\n";
    exit(1);
}
echo "[PASS] login.php accessible (HTTP 200)\n";

preg_match('/name="csrf_token" value="([^"]+)"/', (string)$html, $matches);
$csrfToken = $matches[1] ?? '';
if (empty($csrfToken)) {
    echo "[FAIL] CSRF token not found in login form.\n";
    exit(1);
}
echo "[PASS] CSRF token generated and parsed: " . substr($csrfToken, 0, 8) . "...\n";

// 2. POST login with invalid password
$invalidPost = http_build_query([
    'csrf_token' => $csrfToken,
    'username'   => 'admin',
    'password'   => 'wrongpass'
]);
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HSMS/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $invalidPost);
$invalidResp = curl_exec($ch);
if (!str_contains((string)$invalidResp, 'Invalid username/email or password')) {
    echo "[FAIL] Invalid login was not properly rejected.\n";
    exit(1);
}
echo "[PASS] Invalid login correctly rejected with security message.\n";

// 3. POST login with valid credentials
$validPost = http_build_query([
    'csrf_token' => $csrfToken,
    'username'   => 'admin',
    'password'   => 'admin123'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $validPost);
curl_setopt($ch, CURLOPT_HEADER, true);
$loginResp = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($loginCode !== 302) {
    echo "[FAIL] Successful login did not redirect with 302 (got {$loginCode})\n";
    exit(1);
}
echo "[PASS] Successful login redirected with HTTP 302\n";

// 4. Access dashboard.php with authenticated session
curl_setopt($ch, CURLOPT_URL, 'http://localhost/HSMS/dashboard.php');
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$dashHtml = curl_exec($ch);
$dashCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($dashCode !== 200) {
    echo "[FAIL] dashboard.php HTTP code: {$dashCode}\n";
    exit(1);
}
echo "[PASS] dashboard.php accessed successfully (HTTP 200)\n";

if (!str_contains((string)$dashHtml, 'Green Valley Residency')) {
    echo "[FAIL] Active society 'Green Valley Residency' not found on dashboard.\n";
    exit(1);
}
echo "[PASS] Multi-society context rendered ('Green Valley Residency' displayed)\n";

if (!str_contains((string)$dashHtml, 'Executive Dashboard') || !str_contains((string)$dashHtml, 'Total Plot Inventory')) {
    echo "[FAIL] Dashboard KPI widgets not rendered properly.\n";
    exit(1);
}
echo "[PASS] Dashboard KPI cards and navigation shell rendered completely.\n";

echo "ALL PHASE 1 TESTS PASSED 100%!\n";
curl_close($ch);
