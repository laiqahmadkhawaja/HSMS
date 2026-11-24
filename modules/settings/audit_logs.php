<?php
/**
 * HSMS - Enterprise Audit Trail & Activity Logs
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('settings_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();

$moduleFilter = trim($_GET['module'] ?? '');
$userFilter   = (int)($_GET['user_id'] ?? 0);

$where = ["1=1"];
$params = [];

if (!empty($moduleFilter)) {
    $where[] = "al.module = :mod";
    $params['mod'] = $moduleFilter;
}

if ($userFilter > 0) {
    $where[] = "al.user_id = :uid";
    $params['uid'] = $userFilter;
}

$whereClause = implode(" AND ", $where);

// Fetch recent 100 audit logs
$stmt = $db->prepare("
    SELECT al.*, u.name as user_name, u.username, s.name as society_name
    FROM `audit_logs` al
    LEFT JOIN `users` u ON al.user_id = u.id
    LEFT JOIN `societies` s ON al.society_id = s.id
    WHERE {$whereClause}
    ORDER BY al.id DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch distinct modules for filter
$modules = $db->query("SELECT DISTINCT module FROM `audit_logs` WHERE module IS NOT NULL ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch users for filter
$users = $db->query("SELECT id, name, username FROM `users` ORDER BY name ASC")->fetchAll();

$pageTitle = 'Enterprise Audit Logs';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">System Audit Trail & Compliance</h4>
        <p class="text-muted small mb-0">Immutable records of all administrative actions, financial postings, and data mutations</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-hs mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="module" class="form-select form-select-sm">
                    <option value="">All Functional Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m) ?>" <?= ($moduleFilter === $m) ? 'selected' : '' ?>><?= e(ucfirst($m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All System Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= ($userFilter === (int)$u['id']) ? 'selected' : '' ?>>
                            <?= e($u['name']) ?> (@<?= e($u['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-hs-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Apply Filter
                </button>
                <a href="<?= base_url('modules/settings/audit_logs.php') ?>" class="btn btn-outline-secondary btn-sm">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card-hs">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-clock-history text-primary me-2"></i> Recent 100 Activity Events</span>
        <span class="badge bg-light text-muted border"><?= count($logs) ?> Records Loaded</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 160px;">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Record ID</th>
                        <th>Details / Mutation</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No audit events matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="small text-muted font-monospace">
                                    <?= date('d M Y, h:i:s A', strtotime($l['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($l['user_name'] ?: 'System / Automated') ?></div>
                                    <?php if (!empty($l['username'])): ?>
                                        <div class="small text-muted font-monospace">@<?= e($l['username']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= e($l['action']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e(ucfirst($l['module'])) ?></span>
                                </td>
                                <td class="text-muted small font-monospace">
                                    <?= e($l['record_id'] ?: '—') ?>
                                </td>
                                <td class="small" style="max-width: 320px;">
                                    <?php if (!empty($l['new_values'])): ?>
                                        <div class="bg-light p-1 rounded font-monospace small text-truncate" title="<?= e($l['new_values']) ?>">
                                            <?= e($l['new_values']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?= e($l['ip_address']) ?>
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