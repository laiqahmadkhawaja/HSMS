<?php
/**
 * HSMS - Root Entrypoint Router
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect(base_url('dashboard.php'));
} else {
    redirect(base_url('login.php'));
}
