<?php
/**
 * HSMS - Automated Verification Test for Phase 2 (Master Data & Plot Inventory)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

// Helper for authenticated curl requests
function auth_curl(string $url, ?array $post = null, ?string $fileUploadField = null, ?string $filePath = null): array {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($fileUploadField && $filePath) {
            $post[$fileUploadField] = new CURLFile($filePath, 'text/csv', basename($filePath));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
    }

    $raw = (string)curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    return ['code' => $code, 'headers' => $headers, 'body' => $body];
}

echo "=== STARTING PHASE 2 AUTOMATED TEST SUITE ===\n";

// 1. Verify Master Screens
$screens = [
    'modules/societies/index.php' => 'Housing Societies',
    'modules/phases/index.php'    => 'Phases Management',
    'modules/blocks/index.php'    => 'Blocks Management',
    'modules/plots/index.php'     => 'Plot Inventory'
];

foreach ($screens as $path => $expectedText) {
    $res = auth_curl("http://localhost/HSMS/{$path}");
    if ($res['code'] !== 200 || !str_contains($res['body'], $expectedText)) {
        echo "[FAIL] Screen {$path} failed to load (HTTP {$res['code']})\n";
        exit(1);
    }
    echo "[PASS] Screen {$path} loaded successfully with '{$expectedText}'\n";
}

// 2. Test Cascade AJAX endpoint
$ajaxRes = auth_curl("http://localhost/HSMS/ajax/plots/get_blocks.php?phase_id=1");
if ($ajaxRes['code'] !== 200) {
    echo "[FAIL] AJAX get_blocks returned HTTP {$ajaxRes['code']}\n";
    exit(1);
}
$ajaxData = json_decode($ajaxRes['body'], true);
if (!$ajaxData || !$ajaxData['success'] || empty($ajaxData['data'])) {
    echo "[FAIL] AJAX get_blocks returned invalid payload: {$ajaxRes['body']}\n";
    exit(1);
}
echo "[PASS] AJAX cascade returned " . count($ajaxData['data']) . " active blocks for Phase 1\n";

// 3. Database direct test for Plot Creation & Constraint
$db = get_db_connection();
// Clean previous test plot if exists
$db->exec("DELETE FROM `plots` WHERE `plot_number` IN ('TEST-P2-UNIQUE', 'P2-IMPORT-01', 'P2-IMPORT-02')");

// Fetch CSRF token from plot create page
$createPage = auth_curl("http://localhost/HSMS/modules/plots/create.php");
preg_match('/name="csrf_token" value="([^"]+)"/', $createPage['body'], $m);
$csrf = $m[1] ?? '';

if (empty($csrf)) {
    echo "[FAIL] CSRF token could not be parsed from plots/create.php\n";
    exit(1);
}

// Post new plot
$plotPost = [
    'csrf_token'          => $csrf,
    'phase_id'            => 1,
    'block_id'            => 1,
    'plot_number'         => 'TEST-P2-UNIQUE',
    'plot_type_id'        => 1,
    'plot_size_id'        => 2, // 5 Marla
    'street_road'         => 'Boulevard North',
    'sector_zone'         => 'Zone 1',
    'is_corner'           => 1,
    'base_price'          => '2500000',
    'development_charges' => '400000',
    'additional_charges'  => '100000',
    'discount_allowed'    => '50000',
    'remarks'             => 'Test automated plot'
];

$postRes = auth_curl("http://localhost/HSMS/modules/plots/create.php", $plotPost);
if ($postRes['code'] !== 302) {
    echo "[FAIL] Plot creation did not redirect (HTTP {$postRes['code']}): {$postRes['body']}\n";
    exit(1);
}
echo "[PASS] Plot creation succeeded (HTTP 302 redirect)\n";

// Verify row in database
$stmt = $db->prepare("SELECT * FROM `plots` WHERE `plot_number` = 'TEST-P2-UNIQUE'");
$stmt->execute();
$plot = $stmt->fetch();
if (!$plot) {
    echo "[FAIL] Plot TEST-P2-01 not found in database.\n";
    exit(1);
}
// Net price should be (2,500,000 + 400,000 + 100,000) - 50,000 = 2,950,000
$expectedNet = 2950000.00;
if (abs((float)$plot['total_price'] - $expectedNet) > 0.01) {
    echo "[FAIL] Plot total_price calculated incorrectly: {$plot['total_price']} vs expected {$expectedNet}\n";
    exit(1);
}
echo "[PASS] Plot verified in database with exact net price Rs. " . number_format($expectedNet, 2) . "\n";

// 4. Test duplicate plot rejection in same block
$dupRes = auth_curl("http://localhost/HSMS/modules/plots/create.php", $plotPost);
if (!str_contains($dupRes['body'], 'already exists in the selected block')) {
    echo "[FAIL] Duplicate plot was not rejected by validation.\n";
    exit(1);
}
echo "[PASS] Duplicate plot number in same block correctly rejected.\n";

// 5. Test Price revision history
$editPost = $plotPost;
$editPost['base_price'] = '2700000';
$editPost['price_change_reason'] = 'Phase 2 test price revision';
$editRes = auth_curl("http://localhost/HSMS/modules/plots/edit.php?id=" . $plot['id'], $editPost);

$stmtHist = $db->prepare("SELECT * FROM `plot_price_history` WHERE `plot_id` = :pid ORDER BY id DESC LIMIT 1");
$stmtHist->execute(['pid' => $plot['id']]);
$hist = $stmtHist->fetch();
if (!$hist || abs((float)$hist['new_base_price'] - 2700000.00) > 0.01) {
    echo "[FAIL] Plot price history record was not generated on price change.\n";
    exit(1);
}
echo "[PASS] Price history tracking verified (Old: {$hist['old_base_price']}, New: {$hist['new_base_price']})\n";

// 6. Test Bulk CSV Import
$tempCsv = sys_get_temp_dir() . '/test_plots.csv';
$csvContent = "Phase_Name,Block_Name,Plot_Number,Plot_Type,Plot_Size,Street_Road,Is_Corner,Is_Boulevard,Is_Park_Facing,Base_Price,Development_Charges,Additional_Charges,Remarks\n";
$csvContent .= "Phase 1 - Executive,Block A - Rose,P2-IMPORT-01,Residential,5 Marla,Street 10,0,0,0,2600000,400000,0,Bulk CSV Test 1\n";
$csvContent .= "Phase 1 - Executive,Block B - Tulip,P2-IMPORT-02,Residential,10 Marla,Avenue 2,1,0,0,4800000,600000,0,Bulk CSV Test 2\n";
file_put_contents($tempCsv, $csvContent);

$importPage = auth_curl("http://localhost/HSMS/modules/plots/import.php");
preg_match('/name="csrf_token" value="([^"]+)"/', $importPage['body'], $m);
$importCsrf = $m[1] ?? '';

$importPost = ['csrf_token' => $importCsrf];
$importRes = auth_curl("http://localhost/HSMS/modules/plots/import.php", $importPost, 'csv_file', $tempCsv);
@unlink($tempCsv);

if ($importRes['code'] !== 302) {
    echo "[FAIL] CSV bulk import did not redirect (HTTP {$importRes['code']})\n";
    exit(1);
}

// Verify imported plots in database
$stmtImp = $db->query("SELECT COUNT(*) FROM `plots` WHERE `plot_number` IN ('P2-IMPORT-01', 'P2-IMPORT-02')");
$impCount = (int)$stmtImp->fetchColumn();
if ($impCount !== 2) {
    echo "[FAIL] Expected 2 imported plots in database, found {$impCount}\n";
    exit(1);
}
echo "[PASS] Bulk CSV import verified: 2 plots inserted transactionally.\n";

echo "ALL PHASE 2 MASTER DATA TESTS PASSED 100%!\n";
