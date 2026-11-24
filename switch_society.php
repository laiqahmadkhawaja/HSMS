<?php
/**
 * HSMS - Active Society Switcher
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_login();

$societyId = (int)($_GET['id'] ?? 0);
$csrf = (string)($_GET['csrf'] ?? '');

if (!validate_csrf($csrf)) {
    redirect_with_message(base_url('dashboard.php'), 'Invalid security token for society change.', 'danger');
}

if ($societyId > 0 && set_active_society_id($societyId)) {
    redirect_with_message(base_url('dashboard.php'), 'Active society changed successfully.', 'success');
} else {
    redirect_with_message(base_url('dashboard.php'), 'Unable to switch to the selected society.', 'danger');
}
