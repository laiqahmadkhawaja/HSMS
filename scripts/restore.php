<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$file = $argv[1] ?? '';
if (empty($file) || !file_exists($file)) {
    die("File not found: {$file}\n");
}

$db = get_db_connection();
$sql = file_get_contents($file);
$db->exec($sql);
echo "DATABASE RESTORED CLEANLY FROM: {$file}\n";
