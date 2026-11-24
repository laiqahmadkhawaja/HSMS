<?php
/**
 * HSMS - Recovery Officers & Target Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('recovery_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Handle Officer Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'save_officer') {
        $officerId = (int)($_POST['officer_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $recoveryTarget = (float)($_POST['recovery_target'] ?? 0);
        $assignedBlocks = $_POST['assigned_blocks'] ?? [];
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

        if ($userId <= 0) {
            $errors['user_id'] = 'Please select a valid user.';
        }
        if ($recoveryTarget < 0) {
            $errors['recovery_target'] = 'Recovery target cannot be negative.';
        }

        if (empty($errors)) {
            $blocksJson = json_encode(array_values(array_filter((array)$assignedBlocks)));

            if ($officerId > 0) {
                // Update
                $stmtUpd = $db->prepare("
                    UPDATE `recovery_officers`
                    SET `assigned_blocks` = :blocks,
                        `recovery_target` = :target,
                        `status` = :stat
                    WHERE `id` = :id AND `society_id` = :sid
                ");
                $stmtUpd->execute([
                    'blocks' => $blocksJson,
                    'target' => $recoveryTarget,
                    'stat'   => $status,
                    'id'     => $officerId,
                    'sid'    => $activeSocId
                ]);
                log_audit('RECOVERY_OFFICER_UPDATED', 'recovery_officers', $officerId, null, [
                    'target' => $recoveryTarget,
                    'blocks' => $blocksJson
                ], $activeSocId);
                redirect_with_message(base_url('modules/employees/recovery_officers.php'), 'Recovery officer updated successfully!', 'success');
            } else {
                // Check if user is already an officer in this society
                $stmtCheck = $db->prepare("SELECT id FROM `recovery_officers` WHERE `user_id` = :uid AND `society_id` = :sid LIMIT 1");
                $stmtCheck->execute(['uid' => $userId, 'sid' => $activeSocId]);
                if ($stmtCheck->fetchColumn()) {
                    $errors['user_id'] = 'This user is already designated as a recovery officer in this society.';
                } else {
                    $stmtIns = $db->prepare("
                        INSERT INTO `recovery_officers` (`user_id`, `society_id`, `assigned_blocks`, `recovery_target`, `status`)
                        VALUES (:uid, :sid, :blocks, :target, :stat)
                    ");
                    $stmtIns->execute([
                        'uid'    => $userId,
                        'sid'    => $activeSocId,
                        'blocks' => $blocksJson,
                        'target' => $recoveryTarget,
                        'stat'   => $status
                    ]);
                    $newId = (int)$db->lastInsertId();
                    log_audit('RECOVERY_OFFICER_CREATED', 'recovery_officers', $newId, null, [
                        'user_id' => $userId,
                        'target'  => $recoveryTarget
                    ], $activeSocId);
                    redirect_with_message(base_url('modules/employees/recovery_officers.php'), 'Recovery officer added successfully!', 'success');
                }
            }
        }
    } elseif ($action === 'toggle_status') {
        $officerId = (int)($_POST['officer_id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';

        $stmtToggle = $db->prepare("UPDATE `recovery_officers` SET `status` = :stat WHERE `id` = :id AND `society_id` = :sid");
        $stmtToggle->execute(['stat' => $newStatus, 'id' => $officerId, 'sid' => $activeSocId]);
        log_audit('RECOVERY_OFFICER_STATUS_TOGGLED', 'recovery_officers', $officerId, null, ['status' => $newStatus], $activeSocId);
        redirect_with_message(base_url('modules/employees/recovery_officers.php'), 'Officer status updated to ' . ucfirst($newStatus) . '.', 'success');
    }
}

// Fetch available blocks in active society for multi-select
$stmtBlocks = $db->prepare("
    SELECT b.id, b.block_name, b.block_code, ph.phase_name 
    FROM `blocks` b
    JOIN `phases` ph ON b.phase_id = ph.id
    WHERE b.society_id = :sid AND b.status = 'active'
    ORDER BY ph.id ASC, b.block_name ASC
");
$stmtBlocks->execute(['sid' => $activeSocId]);
$societyBlocks = $stmtBlocks->fetchAll();

// Fetch eligible users for officer dropdown (users who have access to society)
$stmtUsers = $db->prepare("
    SELECT u.id, u.name, u.email, u.phone, r.name as role_name
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `user_society_access` usa ON u.id = usa.user_id AND usa.society_id = :sid
    WHERE (u.is_super_admin = 1 OR usa.society_id = :sid2) AND u.status = 'active'
    ORDER BY u.name ASC
");
$stmtUsers->execute(['sid' => $activeSocId, 'sid2' => $activeSocId]);
$availableUsers = $stmtUsers->fetchAll();

// Fetch Recovery Officers with performance metrics
$stmtOfficers = $db->prepare("
    SELECT ro.*, u.name as officer_name, u.email, u.phone, u.status as user_status,
           (
               SELECT COALESCE(SUM(p.payment_amount), 0)
               FROM `payments` p
               WHERE p.recovery_officer_id = ro.id
                 AND p.society_id = ro.society_id
                 AND p.status = 'posted'
                 AND p.payment_date >= :mstart
                 AND p.payment_date <= :mend
           ) as month_recovered,
           (
               SELECT COUNT(*)
               FROM `payments` p
               WHERE p.recovery_officer_id = ro.id
                 AND p.society_id = ro.society_id
                 AND p.status = 'posted'
                 AND p.payment_date >= :mstart2
                 AND p.payment_date <= :mend2
           ) as month_receipts_count,
           (
               SELECT COUNT(*)
               FROM `promises_to_pay` ptp
               WHERE ptp.recovery_officer_id = ro.id AND ptp.status = 'pending'
           ) as pending_promises_count
    FROM `recovery_officers` ro
    JOIN `users` u ON ro.user_id = u.id
    WHERE ro.society_id = :sid
    ORDER BY ro.status ASC, ro.id DESC
");
$stmtOfficers->execute([
    'mstart'  => $monthStart,
    'mend'    => $monthEnd,
    'mstart2' => $monthStart,
    'mend2'   => $monthEnd,
    'sid'     => $activeSocId
]);
$officers = $stmtOfficers->fetchAll();

$pageTitle = 'Recovery Officers & Performance';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Recovery Officers & Field Staff</h4>
        <p class="text-muted small mb-0">Manage field recovery staff, assigned blocks, and recovery targets for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#officerModal" onclick="prepareAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Add Recovery Officer
    </button>
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

<!-- Performance Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $totalTarget = 0.0;
    $totalRecovered = 0.0;
    foreach ($officers as $ro) {
        if ($ro['status'] === 'active') {
            $totalTarget += (float)$ro['recovery_target'];
            $totalRecovered += (float)$ro['month_recovered'];
        }
    }
    $overallPct = $totalTarget > 0 ? min(100.0, round(($totalRecovered / $totalTarget) * 100, 1)) : 0.0;
    ?>
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Monthly Target (<?= date('F Y') ?>)</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= format_currency($totalTarget) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Current Month Recovered</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= format_currency($totalRecovered) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Overall Recovery Rate</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= $overallPct ?>%</div>
            <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $overallPct ?>%"></div>
            </div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Officer Name</th>
                        <th>Contact</th>
                        <th>Assigned Blocks</th>
                        <th>Monthly Target</th>
                        <th>Recovered (This Month)</th>
                        <th>Target Progress</th>
                        <th>Pending PTPs</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($officers)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No recovery officers configured yet. Click "Add Recovery Officer" above to assign field staff.</td></tr>
                    <?php else: ?>
                        <?php foreach ($officers as $ro): ?>
                            <?php
                            $target = (float)$ro['recovery_target'];
                            $rec = (float)$ro['month_recovered'];
                            $pct = $target > 0 ? min(100.0, round(($rec / $target) * 100, 1)) : 0.0;
                            $blocks = json_decode($ro['assigned_blocks'] ?? '[]', true) ?: [];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= e($ro['officer_name']) ?></div>
                                    <div class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i> ID #RO-<?= (int)$ro['id'] ?></div>
                                </td>
                                <td>
                                    <div><i class="bi bi-telephone me-1 text-muted"></i> <?= e($ro['phone'] ?: 'N/A') ?></div>
                                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i> <?= e($ro['email']) ?></div>
                                </td>
                                <td>
                                    <?php if (empty($blocks)): ?>
                                        <span class="badge bg-light text-muted border">All Blocks</span>
                                    <?php else: ?>
                                        <?php foreach ($blocks as $blk): ?>
                                            <span class="badge bg-secondary me-1"><?= e($blk) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?= format_currency($target) ?></td>
                                <td class="fw-bold text-success">
                                    <?= format_currency($rec) ?>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= (int)$ro['month_receipts_count'] ?> receipts</div>
                                </td>
                                <td style="min-width: 140px;">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="fw-semibold"><?= $pct ?>%</span>
                                        <span class="text-muted"><?= format_currency($rec) ?> / <?= format_currency($target) ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= ($pct >= 100) ? 'bg-success' : (($pct >= 50) ? 'bg-info' : 'bg-warning') ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ((int)$ro['pending_promises_count'] > 0): ?>
                                        <a href="<?= base_url('modules/recoveries/promises.php?officer_id=' . (int)$ro['id']) ?>" class="badge bg-warning text-dark text-decoration-none">
                                            <?= (int)$ro['pending_promises_count'] ?> Pending
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ro['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary" onclick='editOfficer(<?= json_encode($ro, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Change status of this officer?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="officer_id" value="<?= (int)$ro['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= ($ro['status'] === 'active') ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-outline-<?= ($ro['status'] === 'active') ? 'warning' : 'success' ?>" title="<?= ($ro['status'] === 'active') ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Officer -->
<div class="modal fade" id="officerModal" tabindex="-1" aria-labelledby="officerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="officerForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_officer">
                <input type="hidden" name="officer_id" id="modal_officer_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="officerModalLabel">Add Recovery Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="userSelectGroup">
                        <label class="form-label fw-bold">Select Staff User <span class="text-danger">*</span></label>
                        <select name="user_id" id="modal_user_id" class="form-select form-select-sm" required>
                            <option value="">Choose User Account</option>
                            <?php foreach ($availableUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['role_name']) ?>) - <?= e($u['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Monthly Recovery Target (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="recovery_target" id="modal_recovery_target" class="form-control form-control-sm" required placeholder="e.g. 5000000.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Assigned Society Blocks</label>
                        <div class="border rounded p-2 bg-light" style="max-height: 160px; overflow-y: auto;">
                            <?php foreach ($societyBlocks as $sb): ?>
                                <div class="form-check">
                                    <input class="form-check-input block-checkbox" type="checkbox" name="assigned_blocks[]" value="<?= e($sb['block_code']) ?>" id="block_<?= (int)$sb['id'] ?>">
                                    <label class="form-check-label small" for="block_<?= (int)$sb['id'] ?>">
                                        <?= e($sb['block_name']) ?> (<?= e($sb['block_code']) ?>) — <span class="text-muted"><?= e($sb['phase_name']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text small">Leave all unchecked to assign the entire society.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Officer Status</label>
                        <select name="status" id="modal_status" class="form-select form-select-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">Save Officer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareAddModal() {
    document.getElementById('officerModalLabel').innerText = 'Add Recovery Officer';
    document.getElementById('modal_officer_id').value = '0';
    document.getElementById('modal_user_id').disabled = false;
    document.getElementById('modal_user_id').value = '';
    document.getElementById('modal_recovery_target').value = '';
    document.getElementById('modal_status').value = 'active';

    document.querySelectorAll('.block-checkbox').forEach(cb => {
        cb.checked = false;
    });
}

function editOfficer(officer) {
    document.getElementById('officerModalLabel').innerText = 'Edit Recovery Officer: ' + officer.officer_name;
    document.getElementById('modal_officer_id').value = officer.id;
    document.getElementById('modal_user_id').value = officer.user_id;
    document.getElementById('modal_user_id').disabled = true; // Cannot switch underlying user
    document.getElementById('modal_recovery_target').value = officer.recovery_target;
    document.getElementById('modal_status').value = officer.status;

    let assigned = [];
    try {
        assigned = JSON.parse(officer.assigned_blocks) || [];
    } catch(e) {
        assigned = [];
    }

    document.querySelectorAll('.block-checkbox').forEach(cb => {
        cb.checked = assigned.includes(cb.value);
    });

    const modal = new bootstrap.Modal(document.getElementById('officerModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
