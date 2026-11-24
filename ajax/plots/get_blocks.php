<?php
/**
 * HSMS - AJAX Cascade Endpoint: Get Blocks by Phase
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_login();

$phaseId = (int)($_GET['phase_id'] ?? 0);
$socId = get_active_society_id();

if ($phaseId <= 0) {
    json_response(false, 'Invalid phase parameter.', [], [], 400);
}

$db = get_db_connection();
$stmt = $db->prepare("
    SELECT id, block_name, block_code 
    FROM `blocks` 
    WHERE `phase_id` = :pid AND `society_id` = :sid AND `status` = 'active'
    ORDER BY `block_name` ASC
");
$stmt->execute(['pid' => $phaseId, 'sid' => $socId]);
$blocks = $stmt->fetchAll();

json_response(true, 'Blocks loaded', $blocks);
