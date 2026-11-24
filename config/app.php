<?php
/**
 * HSMS - Core Application Configuration & Bootstrapper
 */

declare(strict_types=1);

// Application Metadata
if (!defined('APP_NAME')) define('APP_NAME', 'Housing Society Management System');
if (!defined('APP_SHORT_NAME')) define('APP_SHORT_NAME', 'HSMS ERP');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0-PROD');

// Environment & Debugging (Set to false for production error suppression)
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);

// Set Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Log errors safely
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . '/backups/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/hsms_error.log');

// Date & Timezone
date_default_timezone_set('Asia/Karachi');

// Base Paths
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', BASE_PATH . '/config');
if (!defined('INCLUDES_PATH')) define('INCLUDES_PATH', BASE_PATH . '/includes');
if (!defined('SERVICES_PATH')) define('SERVICES_PATH', BASE_PATH . '/services');
if (!defined('MODULES_PATH')) define('MODULES_PATH', BASE_PATH . '/modules');
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Load Core Dependencies
require_once CONFIG_PATH . '/constants.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/csrf.php';
require_once INCLUDES_PATH . '/validation.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/permissions.php';
