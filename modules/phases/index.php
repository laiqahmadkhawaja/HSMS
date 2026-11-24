<?php
/**
 * HSMS - Phases Master Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('masters_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

// Handle Phase Form Submission (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $action = $_POST['action'] ?? 'create';
    $phaseId = (int)($_POST['phase_id'] ?? 0);
    $name = trim($_POST['phase_name'] ?? '');
    $code = strtoupper(trim($_POST['phase_code'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($name === '' || $code === '') {
        $errors['general'] = 'Phase Name and Phase Code are required.';
    } else {
        if ($action === 'create') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `phases` WHERE `society_id` = :sid AND `phase_name` = :name");
            $stmt->execute(['sid' => $activeSocId, 'name' => $name]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors['general'] = 'A phase with this name already exists in this society.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO `phases` (`society_id`, `phase_name`, `phase_code`, `description`, `status`)
                    VALUES (:sid, :name, :code, :desc, :status)
                ");
                $stmt->execute(['sid' => $activeSocId, 'name' => $name, 'code' => $code, 'desc' => $desc ?: null, 'status' => $status]);
                log_audit('PHASE_CREATED', 'phases', (int)$db->lastInsertId(), null, ['name' => $name], $activeSocId);
                redirect_with_message(base_url('modules/phases/index.php'), "Phase '{$name}' created successfully!", 'success');
            }
        } elseif ($action === 'update' && $phaseId > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `phases` WHERE `society_id` = :sid AND `phase_name` = :name AND `id` != :id");
            $stmt->execute(['sid' => $activeSocId, 'name' => $name, 'id' => $phaseId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors['general'] = 'Another phase with this name already exists in this society.';
            } else {
                $stmt = $db->prepare("
                    UPDATE `phases` SET `phase_name` = :name, `phase_code` = :code, `description` = :desc, `status` = :status
                    WHERE `id` = :id AND `society_id` = :sid
                ");
                $stmt->execute(['name' => $name, 'code' => $code, 'desc' => $desc ?: null, 'status' => $status, 'id' => $phaseId, 'sid' => $activeSocId]);
                log_audit('PHASE_UPDATED', 'phases', $phaseId, null, ['name' => $name], $activeSocId);
                redirect_with_message(base_url('modules/phases/index.php'), "Phase updated successfully!", 'success');
            }
        }
    }
}

// Fetch Phases for Active Society
$stmt = $db->prepare("
    SELECT p.*,
        (SELECT COUNT(*) FROM `blocks` WHERE `phase_id` = p.id) as blocks_count,
        (SELECT COUNT(*) FROM `plots` WHERE `phase_id` = p.id) as plots_count
    FROM `phases` p
    WHERE p.society_id = :sid
    ORDER BY p.id ASC
");
$stmt->execute(['sid' => $activeSocId]);
$phases = $stmt->fetchAll();

$pageTitle = 'Phases Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Phases Management</h4>
        <p class="text-muted small mb-0">Project phases configured for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <button class="btn btn-hs-primary btn-sm" data-bs-toggle="modal" data-bs-target="#phaseModal" onclick="preparePhaseModal('create')">
        <i class="bi bi-plus-circle me-1"></i> Add New Phase
    </button>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger py-2 small"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card-hs">
    <div class="table-responsive">
        <table class="table table-hs">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Phase Name</th>
                    <th>Description</th>
                    <th>Blocks</th>
                    <th>Plots</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($phases)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No phases configured for this society.</td></tr>
                <?php else: ?>
                    <?php foreach ($phases as $ph): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= e($ph['phase_code']) ?></span></td>
                            <td class="fw-semibold"><?= e($ph['phase_name']) ?></td>
                            <td><?= e($ph['description'] ?? '—') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= (int)$ph['blocks_count'] ?></span></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info fw-bold"><?= (int)$ph['plots_count'] ?></span></td>
                            <td>
                                <span class="badge <?= ($ph['status'] === 'active') ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ucfirst(e($ph['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                                        onclick="editPhase(<?= htmlspecialchars(json_encode($ph), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Phase Modal -->
<div class="modal fade" id="phaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="modal_action" value="create">
                <input type="hidden" name="phase_id" id="modal_phase_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalTitle">Add New Phase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Phase Name <span class="text-danger">*</span></label>
                        <input type="text" name="phase_name" id="modal_phase_name" class="form-control form-control-sm" required placeholder="e.g. Phase 1 - Executive">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phase Code <span class="text-danger">*</span></label>
                        <input type="text" name="phase_code" id="modal_phase_code" class="form-control form-control-sm" required placeholder="e.g. PH1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="modal_status" class="form-select form-select-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="modal_description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-hs-primary">Save Phase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function preparePhaseModal(mode) {
    document.getElementById('modal_action').value = 'create';
    document.getElementById('modal_phase_id').value = '0';
    document.getElementById('modalTitle').textContent = 'Add New Phase';
    document.getElementById('modal_phase_name').value = '';
    document.getElementById('modal_phase_code').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_status').value = 'active';
}

function editPhase(ph) {
    document.getElementById('modal_action').value = 'update';
    document.getElementById('modal_phase_id').value = ph.id;
    document.getElementById('modalTitle').textContent = 'Edit Phase: ' + ph.phase_name;
    document.getElementById('modal_phase_name').value = ph.phase_name;
    document.getElementById('modal_phase_code').value = ph.phase_code;
    document.getElementById('modal_description').value = ph.description || '';
    document.getElementById('modal_status').value = ph.status;
    
    const modal = new bootstrap.Modal(document.getElementById('phaseModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
