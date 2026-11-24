<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/backup_service.php';

$label = $argv[1] ?? 'manual_backup';
$path = BackupService::createBackup($label);
echo "BACKUP CREATED SUCCESSFULLY: {$path}\n";
