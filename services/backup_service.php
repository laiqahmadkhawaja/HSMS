<?php
/**
 * HSMS - Database Backup & Restoration Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class BackupService {
    /**
     * Generate complete SQL backup file
     */
    public static function createBackup(string $prefix = 'backup'): string {
        $db = get_db_connection();
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $backupDir = BASE_PATH . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $fileName = sprintf('%s_%s_%s.sql', $prefix, date('Ymd_His'), bin2hex(random_bytes(2)));
        $filePath = $backupDir . '/' . $fileName;

        $handle = fopen($filePath, 'w');
        if (!$handle) {
            throw new RuntimeException("Unable to open backup file for writing at {$filePath}");
        }

        fwrite($handle, "-- HSMS Database Automated Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: hsms_db\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            // Table structure
            $createTableStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $createSql = $createTableStmt['Create Table'] ?? '';
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql . ";\n\n");

            // Table rows
            $rowsStmt = $db->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $colNames = implode('`, `', $columns);

                foreach ($rows as $row) {
                    $escapedVals = array_map(function ($val) use ($db) {
                        if ($val === null) return 'NULL';
                        return $db->quote((string)$val);
                    }, array_values($row));
                    $valString = implode(', ', $escapedVals);
                    fwrite($handle, "INSERT INTO `{$table}` (`{$colNames}`) VALUES ({$valString});\n");
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        log_audit('DATABASE_BACKED_UP', 'backup', null, null, ['file' => $fileName]);
        return $filePath;
    }
}
