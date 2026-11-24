<?php
/**
 * HSMS - Database Installer & Migrator Script
 * Can be run via CLI (`php install.php`) or Web browser
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'hsms_db';

$isCli = (php_sapi_name() === 'cli');

function output_msg(string $msg, bool $isCli, string $type = 'info'): void {
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warn'    => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#198754',
            'error'   => '#dc3545',
            'warn'    => '#ffc107',
            default   => '#0d6efd'
        };
        echo "<div style='font-family:sans-serif; margin: 4px 0; color: {$color};'>" . htmlspecialchars($msg) . "</div>";
    }
}

try {
    output_msg("Connecting to MySQL server at {$host}:{$port}...", $isCli);
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    output_msg("Database connection established successfully.", $isCli, 'success');

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    output_msg("Database `{$dbname}` verified / created.", $isCli, 'success');

    // Switch to database
    $pdo->exec("USE `{$dbname}`");

    // Load and run schema.sql
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found at: {$schemaFile}");
    }
    output_msg("Executing schema migrations...", $isCli);
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    output_msg("Schema migrations applied successfully.", $isCli, 'success');

    // Load and run seed.sql
    $seedFile = __DIR__ . '/database/seed.sql';
    if (file_exists($seedFile)) {
        output_msg("Executing seed data...", $isCli);
        $seedSql = file_get_contents($seedFile);
        $pdo->exec($seedSql);
        output_msg("Seed data loaded successfully.", $isCli, 'success');
    }

    // Ensure admin user password is valid BCrypt hash for 'admin123'
    $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
    $staffHash = password_hash('password123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `username` = 'admin'");
    $stmt->execute(['hash' => $adminHash]);

    $stmt = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `username` != 'admin'");
    $stmt->execute(['hash' => $staffHash]);

    output_msg("Default admin user verified: username = 'admin', password = 'admin123'", $isCli, 'success');
    output_msg("Demo staff users updated: password = 'password123'", $isCli, 'success');
    output_msg("Installation & verification completed 100% successfully!", $isCli, 'success');

} catch (Throwable $e) {
    output_msg("Installation Failed: " . $e->getMessage(), $isCli, 'error');
    if ($isCli) {
        exit(1);
    }
}
