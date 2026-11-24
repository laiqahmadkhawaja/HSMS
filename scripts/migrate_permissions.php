<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db_connection();
$db->exec("
    INSERT INTO `permissions` (`permission_code`, `module`, `description`)
    VALUES ('users_manage', 'users', 'Create and manage user accounts and role assignments')
    ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
");

$db->exec("
    INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
    SELECT 1, `id` FROM `permissions` WHERE `permission_code` = 'users_manage';
");

$db->exec("
    INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
    SELECT 2, `id` FROM `permissions` WHERE `permission_code` = 'users_manage';
");

echo "Permissions migration successfully executed.\n";
