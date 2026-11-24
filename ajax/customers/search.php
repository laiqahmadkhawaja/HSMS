<?php
/**
 * HSMS - AJAX Customer Search Endpoint for Autocomplete
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_login();

$term = trim($_GET['q'] ?? '');
if (strlen($term) < 2) {
    json_response(true, 'Query too short', []);
}

$db = get_db_connection();
$param = "%{$term}%";
$stmt = $db->prepare("
    SELECT id, customer_id_code, full_name, father_husband_name, cnic_passport, mobile, city
    FROM `customers`
    WHERE status = 'active'
      AND (full_name LIKE :q1 OR cnic_passport LIKE :q2 OR mobile LIKE :q3 OR customer_id_code LIKE :q4)
    ORDER BY full_name ASC
    LIMIT 15
");
$stmt->execute([
    'q1' => $param,
    'q2' => $param,
    'q3' => $param,
    'q4' => $param
]);
$results = $stmt->fetchAll();

json_response(true, 'Customers loaded', $results);
