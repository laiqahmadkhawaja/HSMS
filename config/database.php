<?php
/**
 * HSMS - Database Connection Configuration
 * Uses PHP PDO with strict prepared statements and error prevention
 */

declare(strict_types=1);

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dbConfig = [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'dbname'  => 'hsms_db',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4'
    ];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
    ];

    try {
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log detailed error internally
        error_log('[HSMS DB Error] ' . $e->getMessage());

        // In production or safe display, never leak credentials
        if (defined('APP_DEBUG') && APP_DEBUG) {
            die('Database Connection Error: ' . htmlspecialchars($e->getMessage()));
        } else {
            die('A database connection error occurred. Please contact the system administrator.');
        }
    }
}
