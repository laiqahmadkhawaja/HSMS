<?php
/**
 * HSMS - Blocks Master Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('masters_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $action = $_POST['action'] ?? 'create';
    $blockId = (int)($_POST['block_id'] ?? 0);
    $phaseId = (int)($_POST['phase_id'] ?? 0);
    $name = trim($_POST['block_name'] ?? '');
    $code = strtoupper(trim($_POST['block_code'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($phaseId <= 0 || $name === '' || $code === '') {
        $errors['general'] = 'Phase, Block Name, and Block Code are required.';
    } else {
        if ($action === 'create') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `blocks` WHERE `phase_id` = :pid AND `block_name` = :name");
            $stmt->execute(['pid' => $phaseId, 'name' => $name]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors['general'] = 'A block with this name already exists in the selected phase.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO `blocks` (`society_id`, `phase_id`, `block_name`, `block_code`, `description`, `status`)
                    VALUES (:sid, :pid, :name, :code, :desc, :status)
                ");
                $stmt->execute(['sid' => $activeSocId, 'pid' => $phaseId, 'name' => $name, 'code' => $code, 'desc' => $desc ?: null, 'status' => $status]);
                log_audit('BLOCK_CREATED', 'blocks', (int)$db->lastInsertId(), null, ['name' => $name], $activeSocId);
                redirect_with_message(base_url('modules/blocks/index.php'), "Block '{$name}' created successfully!", 'success');
            }
        } elseif ($action === 'update' && $blockId > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `blocks` WHERE `phase_id` = :pid AND `block_name` = :name AND `id` != :id");
            $stmt->execute(['pid' => $phaseId, 'name' => $name, 'id' => $blockId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors['general'] = 'Another block with this name already exists in the selected phase.';
            } else {
                $stmt = $db->prepare("
                    UPDATE `blocks` SET `phase_id` = :pid, `block_name` = :name, `block_code` = :code, `description` = :desc, `status` = :status
                    WHERE `id` = :id AND `society_id` = :sid
                ");
                $stmt->execute(['pid' => $phaseId, 'name' => $name, 'code' => $code, 'desc' => $desc ?: null, 'status' => $status, 'id' => $blockId, 'sid' => $activeSocId]);
                log_audit('BLOCK_UPDATED', 'blocks', $blockId, null, ['name' => $name], $activeSocId);
                redirect_with_message(base_url('modules/blocks/index.php'), "Block updated successfully!", 'success');
            }
        }
    }
}

// Fetch all phases for dropdown
$stmt = $db->prepare("SELECT id, phase_name FROM `phases` WHERE `society_id` = :sid AND `status` = 'active' ORDER BY `phase_name` ASC");
$stmt->execute(['sid' => $activeSocId]);
$phases = $stmt->fetchAll();

// Fetch Blocks
$phaseFilter = (int)($_GET['phase_id'] ?? 0);
$sql = "
    SELECT b.*, p.phase_name,
        (SELECT COUNT(*) FROM `plots` WHERE `block_id` = b.id) as plots_count,
        (SELECT COUNT(*) FROM `plots` WHERE `block_id` = b.id AND `status` = 'available') as available_plots
    FROM `blocks` b
    JOIN `phases` p ON b.phase_id = p.id
    WHERE b.society_id = :sid
";
$params = ['sid' => $activeSocId];
if ($phaseFilter > 0) {
    $sql .= " AND b.phase_id = :pid";
    $params['pid'] = $phaseFilter;
}
$sql .= " ORDER BY p.id ASC, b.block_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$blocks = $stmt->fetchAll();

$pageTitle = 'Blocks Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Blocks Management</h4>
        <p class="text-muted small mb-0">Blocks and residential sectors for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <button class="btn btn-hs-primary btn-sm" data-bs-toggle="modal" data-bs-target="#blockModal" onclick="prepareBlockModal('create')">
        <i class="bi bi-plus-circle me-1"></i> Add New Block
    </button>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger py-2 small"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <select name="phase_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Phases</option>
                    <?php foreach ($phases as $ph): ?>
                        <option value="<?= (int)$ph['id'] ?>" <?= ($phaseFilter === (int)$ph['id']) ? 'selected' : '' ?>>
                            <?= e($ph['phase_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($phaseFilter > 0): ?>
                <div class="col-md-2">
                    <a href="<?= base_url('modules/blocks/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset Filter</a>
                </div>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Block Code</th>
                        <th>Block Name</th>
                        <th>Parent Phase</th>
                        <th>Total Plots</th>
                        <th>Available Plots</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocks)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No blocks found for the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($blocks as $blk): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($blk['block_code']) ?></span></td>
                                <td class="fw-semibold"><?= e($blk['block_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($blk['phase_name']) ?></span></td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= (int)$blk['plots_count'] ?></span></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success fw-bold"><?= (int)$blk['available_plots'] ?></span></td>
                                <td>
                                    <span class="badge <?= ($blk['status'] === 'active') ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst(e($blk['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                                            onclick="editBlock(<?= htmlspecialchars(json_encode($blk), ENT_QUOTES, 'UTF-8') ?>)">
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
</div>

<!-- Add/Edit Block Modal -->
<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="modal_block_action" value="create">
                <input type="hidden" name="block_id" id="modal_block_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="blockModalTitle">Add New Block</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Parent Phase <span class="text-danger">*</span></label>
                        <select name="phase_id" id="modal_block_phase_id" class="form-select form-select-sm" required>
                            <option value="">Select Phase</option>
                            <?php foreach ($phases as $ph): ?>
                                <option value="<?= (int)$ph['id'] ?>"><?= e($ph['phase_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Block Name <span class="text-danger">*</span></label>
                        <input type="text" name="block_name" id="modal_block_name" class="form-control form-control-sm" required placeholder="e.g. Block A - Rose">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Block Code <span class="text-danger">*</span></label>
                        <input type="text" name="block_code" id="modal_block_code" class="form-control form-control-sm" required placeholder="e.g. BLK-A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="modal_block_status" class="form-select form-select-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="modal_block_description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-hs-primary">Save Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareBlockModal(mode) {
    document.getElementById('modal_block_action').value = 'create';
    document.getElementById('modal_block_id').value = '0';
    document.getElementById('blockModalTitle').textContent = 'Add New Block';
    document.getElementById('modal_block_name').value = '';
    document.getElementById('modal_block_code').value = '';
    document.getElementById('modal_block_description').value = '';
    document.getElementById('modal_block_status').value = 'active';
}

function editBlock(blk) {
    document.getElementById('modal_block_action').value = 'update';
    document.getElementById('modal_block_id').value = blk.id;
    document.getElementById('blockModalTitle').textContent = 'Edit Block: ' + blk.block_name;
    document.getElementById('modal_block_phase_id').value = blk.phase_id;
    document.getElementById('modal_block_name').value = blk.block_name;
    document.getElementById('modal_block_code').value = blk.block_code;
    document.getElementById('modal_block_description').value = blk.description || '';
    document.getElementById('modal_block_status').value = blk.status;
    
    const modal = new bootstrap.Modal(document.getElementById('blockModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
