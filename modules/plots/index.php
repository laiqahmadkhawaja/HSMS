<?php
/**
 * HSMS - Plot Inventory Listing & Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('plots_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$phaseFilter = (int)($_GET['phase_id'] ?? 0);
$blockFilter = (int)($_GET['block_id'] ?? 0);
$typeFilter = (int)($_GET['type_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT p.*, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name
    FROM `plots` p
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    WHERE p.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (p.plot_number LIKE :search OR p.street_road LIKE :search)";
    $params['search'] = "%{$search}%";
}
if ($phaseFilter > 0) {
    $sql .= " AND p.phase_id = :pid";
    $params['pid'] = $phaseFilter;
}
if ($blockFilter > 0) {
    $sql .= " AND p.block_id = :bid";
    $params['bid'] = $blockFilter;
}
if ($typeFilter > 0) {
    $sql .= " AND p.plot_type_id = :tid";
    $params['tid'] = $typeFilter;
}
if ($statusFilter !== '') {
    $sql .= " AND p.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY ph.phase_name ASC, b.block_name ASC, p.plot_number ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$plots = $stmt->fetchAll();

// Fetch filter options
$phases = $db->prepare("SELECT id, phase_name FROM `phases` WHERE `society_id` = :sid AND `status` = 'active' ORDER BY `phase_name` ASC");
$phases->execute(['sid' => $activeSocId]);
$allPhases = $phases->fetchAll();

$blocks = $db->prepare("SELECT id, block_name FROM `blocks` WHERE `society_id` = :sid AND `status` = 'active' ORDER BY `block_name` ASC");
$blocks->execute(['sid' => $activeSocId]);
$allBlocks = $blocks->fetchAll();

$types = $db->query("SELECT id, name FROM `plot_types` WHERE `status` = 'active' ORDER BY `name` ASC")->fetchAll();

$pageTitle = 'Plot Inventory Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Inventory</h4>
        <p class="text-muted small mb-0">Master inventory registry for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/plots/import.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk CSV Import
        </a>
        <a href="<?= base_url('modules/plots/create.php') ?>" class="btn btn-hs-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Single Plot
        </a>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Plot # or Street..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="phase_id" class="form-select form-select-sm">
                    <option value="">All Phases</option>
                    <?php foreach ($allPhases as $ph): ?>
                        <option value="<?= (int)$ph['id'] ?>" <?= ($phaseFilter === (int)$ph['id']) ? 'selected' : '' ?>><?= e($ph['phase_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="block_id" class="form-select form-select-sm">
                    <option value="">All Blocks</option>
                    <?php foreach ($allBlocks as $blk): ?>
                        <option value="<?= (int)$blk['id'] ?>" <?= ($blockFilter === (int)$blk['id']) ? 'selected' : '' ?>><?= e($blk['block_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="available" <?= ($statusFilter === 'available') ? 'selected' : '' ?>>Available</option>
                    <option value="reserved" <?= ($statusFilter === 'reserved') ? 'selected' : '' ?>>Reserved</option>
                    <option value="booked" <?= ($statusFilter === 'booked') ? 'selected' : '' ?>>Booked</option>
                    <option value="sold" <?= ($statusFilter === 'sold') ? 'selected' : '' ?>>Sold</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/plots/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Plot #</th>
                        <th>Phase & Block</th>
                        <th>Type & Size</th>
                        <th>Features</th>
                        <th>Base Price</th>
                        <th>Net Total Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plots)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No plots found matching the filter criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($plots as $p): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/plots/view.php?id=' . (int)$p['id']) ?>" class="fw-bold text-decoration-none">
                                        <?= e($p['plot_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($p['block_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['phase_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($p['size_name']) ?></span>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($p['type_name']) ?></span>
                                </td>
                                <td>
                                    <?php if ($p['is_corner']): ?><span class="badge bg-info text-dark" style="font-size:0.7rem;">Corner</span><?php endif; ?>
                                    <?php if ($p['is_boulevard']): ?><span class="badge bg-primary text-white" style="font-size:0.7rem;">Boulevard</span><?php endif; ?>
                                    <?php if ($p['is_park_facing']): ?><span class="badge bg-success text-white" style="font-size:0.7rem;">Park Facing</span><?php endif; ?>
                                    <?php if (!$p['is_corner'] && !$p['is_boulevard'] && !$p['is_park_facing']): ?><span class="text-muted small">Standard</span><?php endif; ?>
                                </td>
                                <td><?= format_currency($p['base_price']) ?></td>
                                <td class="fw-bold text-dark"><?= format_currency($p['total_price']) ?></td>
                                <td>
                                    <span class="status-badge badge-<?= e($p['status']) ?>">
                                        <?= ucfirst(e($p['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/plots/view.php?id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Specifications">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url('modules/plots/edit.php?id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit Plot">
                                        <i class="bi bi-pencil"></i>
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
