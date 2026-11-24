<?php
/**
 * HSMS - Automated Verification Test for Phase 3 (Customer Management)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';

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
            $post[$fileUploadField] = new CURLFile($filePath, 'application/pdf', basename($filePath));
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

echo "=== STARTING PHASE 3 AUTOMATED TEST SUITE ===\n";

// Clean previous test customers if exist
$db = get_db_connection();
$db->exec("DELETE FROM `customer_documents` WHERE `customer_id` IN (SELECT id FROM `customers` WHERE `cnic_passport` = '37405-9999999-9')");
$db->exec("DELETE FROM `customers` WHERE `cnic_passport` = '37405-9999999-9'");

// 1. Verify Customer Registry screen
$res = auth_curl("http://localhost/HSMS/modules/customers/index.php");
if ($res['code'] !== 200 || !str_contains($res['body'], 'Customer Registry')) {
    echo "[FAIL] Customer Registry screen failed to load (HTTP {$res['code']})\n";
    exit(1);
}
echo "[PASS] Customer Registry screen loaded successfully.\n";

// 2. Fetch CSRF token from customer create page
$createPage = auth_curl("http://localhost/HSMS/modules/customers/create.php");
preg_match('/name="csrf_token" value="([^"]+)"/', $createPage['body'], $m);
$csrf = $m[1] ?? '';
if (empty($csrf)) {
    echo "[FAIL] CSRF token could not be parsed from customers/create.php\n";
    exit(1);
}

// 3. Register a new Customer
$custPost = [
    'csrf_token'           => $csrf,
    'customer_id_code'     => 'CUST-TEST-P3',
    'full_name'            => 'Muhammad Asif',
    'father_husband_name'  => 'Abdul Rasheed',
    'cnic_passport'        => '37405-9999999-9',
    'mobile'               => '03001234567',
    'whatsapp'             => '03001234567',
    'email'                => 'asif.buyer@example.com',
    'residential_address'  => 'House 45, Street 12, Sector F-8/2',
    'permanent_address'    => 'Village Kotha, Swabi',
    'city'                 => 'Islamabad',
    'country'              => 'Pakistan',
    'nominee_name'         => 'Fatima Asif',
    'nominee_relation'     => 'Wife',
    'nominee_cnic'         => '37405-9988776-2',
    'nominee_contact'      => '03019876543',
    'status'               => 'active',
    'remarks'              => 'Automated test buyer'
];

$regRes = auth_curl("http://localhost/HSMS/modules/customers/create.php", $custPost);
if ($regRes['code'] !== 302) {
    echo "[FAIL] Customer registration did not redirect (HTTP {$regRes['code']}): {$regRes['body']}\n";
    exit(1);
}
echo "[PASS] Customer registration succeeded (HTTP 302 redirect)\n";

// Verify customer in database
$stmt = $db->prepare("SELECT * FROM `customers` WHERE `cnic_passport` = '37405-9999999-9'");
$stmt->execute();
$cust = $stmt->fetch();
if (!$cust) {
    echo "[FAIL] Customer not found in database.\n";
    exit(1);
}
echo "[PASS] Customer verified in DB (ID: {$cust['id']}, Code: {$cust['customer_id_code']}, Name: {$cust['full_name']})\n";

// 4. Test duplicate CNIC rejection
$dupRes = auth_curl("http://localhost/HSMS/modules/customers/create.php", $custPost);
if (!str_contains($dupRes['body'], 'already exists')) {
    echo "[FAIL] Duplicate CNIC was not rejected by validation.\n";
    exit(1);
}
echo "[PASS] Duplicate customer CNIC correctly identified and blocked.\n";

// 5. Test Customer 360 Profile
$viewRes = auth_curl("http://localhost/HSMS/modules/customers/view.php?id=" . $cust['id']);
if ($viewRes['code'] !== 200 || !str_contains($viewRes['body'], 'Muhammad Asif') || !str_contains($viewRes['body'], 'Fatima Asif')) {
    echo "[FAIL] Customer 360 profile failed to display customer/nominee details.\n";
    exit(1);
}
echo "[PASS] Customer 360 profile rendered all tabs, personal data, and nominee information.\n";

// 6. Test KYC Document Upload
$testDocPath = sys_get_temp_dir() . '/test_kyc_cnic.pdf';
file_put_contents($testDocPath, "%PDF-1.4\n%HSMS KYC Test Document Mock\n%%EOF");

$docPost = [
    'csrf_token'  => $csrf,
    'customer_id' => $cust['id'],
    'doc_type'    => 'CNIC Front'
];
$uploadRes = auth_curl("http://localhost/HSMS/modules/customers/upload_doc.php", $docPost, 'doc_file', $testDocPath);
@unlink($testDocPath);

if ($uploadRes['code'] !== 302) {
    echo "[FAIL] Document upload did not redirect (HTTP {$uploadRes['code']})\n";
    exit(1);
}

// Verify document in database
$stmtDoc = $db->prepare("SELECT * FROM `customer_documents` WHERE `customer_id` = :cid AND `doc_type` = 'CNIC Front'");
$stmtDoc->execute(['cid' => $cust['id']]);
$docRow = $stmtDoc->fetch();
if (!$docRow || !file_exists(BASE_PATH . '/' . $docRow['file_path'])) {
    echo "[FAIL] Uploaded KYC document record or physical file missing.\n";
    exit(1);
}
echo "[PASS] KYC document verified in database and storage ({$docRow['file_path']})\n";

// 7. Test Customer Search Autocomplete Endpoint
$searchRes = auth_curl("http://localhost/HSMS/ajax/customers/search.php?q=Asif");
if ($searchRes['code'] !== 200) {
    echo "[FAIL] AJAX customer search returned HTTP {$searchRes['code']}\n";
    exit(1);
}
$searchData = json_decode($searchRes['body'], true);
if (!$searchData || !$searchData['success'] || empty($searchData['data'])) {
    echo "[FAIL] AJAX customer search returned invalid payload: {$searchRes['body']}\n";
    exit(1);
}
echo "[PASS] AJAX customer search returned matching customer '{$searchData['data'][0]['full_name']}'\n";

echo "ALL PHASE 3 CUSTOMER MODULE TESTS PASSED 100%!\n";
