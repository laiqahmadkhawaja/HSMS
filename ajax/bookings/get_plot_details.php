<?php
/**
 * HSMS - AJAX Endpoint: Get Available Plot Details
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_login();

$plotId = (int)($_GET['plot_id'] ?? 0);
$activeSocId = get_active_society_id();

if ($plotId <= 0) {
    json_response(false, 'Invalid plot ID', [], [], 400);
}

$db = get_db_connection();
$stmt = $db->prepare("
    SELECT p.*, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name
    FROM `plots` p
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    WHERE p.id = :pid AND p.society_id = :sid
    LIMIT 1
");
$stmt->execute(['pid' => $plotId, 'sid' => $activeSocId]);
$plot = $stmt->fetch();

if (!$plot) {
    json_response(false, 'Plot not found in active society.', [], [], 404);
}

json_response(true, 'Plot details retrieved', $plot);
