<?php
/**
 * HSMS - Database Backup & Disaster Recovery Interface
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/backup_service.php';
require_permission('settings_manage');

$backupDir = __DIR__ . '/../../backups';
$errors = [];

// Handle Download
if (isset($_GET['download'])) {
    $filename = basename((string)$_GET['download']);
    $filepath = $backupDir . '/' . $filename;
    if (file_exists($filepath) && str_ends_with($filename, '.sql')) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// Handle Manual Backup Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_backup') {
        $label = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($_POST['label'] ?? 'manual')) ?: 'manual';
        $res = BackupService::createBackup($label);

        if ($res['success']) {
            redirect_with_message(base_url('modules/settings/backup.php'), "Database backup successfully created: {$res['filename']}", 'success');
        } else {
            $errors[] = "Backup creation failed: " . ($res['error'] ?? 'Unknown error');
        }
    }
}

// Scan backups directory
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    if ($files !== false) {
        foreach ($files as $f) {
            if (str_ends_with($f, '.sql')) {
                $p = $backupDir . '/' . $f;
                $backupFiles[] = [
                    'name' => $f,
                    'size' => filesize($p),
                    'time' => filemtime($p)
                ];
            }
        }
    }
}
usort($backupFiles, fn($a, $b) => $b['time'] <=> $a['time']);

$pageTitle = 'Database Backup & Recovery';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Database Backup & Disaster Recovery</h4>
        <p class="text-muted small mb-0">Create point-in-time SQL snapshots and download secure database dumps</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Quick Backup Card -->
    <div class="col-lg-5">
        <div class="card-hs h-100">
            <div class="card-header bg-dark text-white d-flex align-items-center">
                <i class="bi bi-database-down fs-5 me-2"></i>
                <span class="fw-bold">Trigger Instant Database Backup</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Backups capture all 35 schema tables, customer records, accounting ledgers, and transactions into an isolated, timestamped SQL dump.
                </p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_backup">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Backup Label / Note</label>
                        <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. pre_month_end_close" value="manual_snapshot">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-cloud-arrow-down me-1"></i> Generate SQL Backup Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Storage Stats -->
    <div class="col-lg-7">
        <div class="card-hs h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-hdd-network text-primary me-2"></i> Backup Storage Information</span>
                <span class="badge bg-light text-muted border"><?= count($backupFiles) ?> Stored Snapshots</span>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Total Backups</div>
                            <div class="fs-4 fw-bold text-dark"><?= count($backupFiles) ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Total Size</div>
                            <div class="fs-4 fw-bold text-dark">
                                <?= number_format(array_sum(array_column($backupFiles, 'size')) / 1024, 1) ?> KB
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small">Latest Snapshot</div>
                            <div class="small fw-bold text-dark mt-1">
                                <?= !empty($backupFiles) ? date('d M, h:i A', $backupFiles[0]['time']) : 'None' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i> Backups are securely stored in <code>c:\wamp64\www\HSMS\backups\</code> with directory listing disabled via <code>.htaccess</code>.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backups List -->
<div class="card-hs">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i> Available Database Snapshots</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th>Snapshot Filename</th>
                        <th>File Size</th>
                        <th>Created Timestamp</th>
                        <th class="text-end">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backupFiles)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No backups found in directory.</td></tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $bf): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark font-monospace"><?= e($bf['name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= number_format($bf['size'] / 1024, 1) ?> KB</span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y, h:i:s A', $bf['time']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/settings/backup.php?download=' . urlencode($bf['name'])) ?>" class="btn btn-outline-primary btn-sm py-0 px-2 fw-semibold">
                                        <i class="bi bi-download me-1"></i> Download SQL
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>