<?php
/**
 * HSMS - Societies Master Listing
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('societies_manage');

$db = get_db_connection();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM `phases` WHERE `society_id` = s.id) AS phases_count,
        (SELECT COUNT(*) FROM `blocks` WHERE `society_id` = s.id) AS blocks_count,
        (SELECT COUNT(*) FROM `plots` WHERE `society_id` = s.id) AS plots_count
        FROM `societies` s WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.name LIKE :search OR s.society_code LIKE :search OR s.location LIKE :search)";
    $params['search'] = "%{$search}%";
}
if ($statusFilter !== '') {
    $sql .= " AND s.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY s.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$societies = $stmt->fetchAll();

$pageTitle = 'Housing Societies / Projects';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Housing Societies</h4>
        <p class="text-muted small mb-0">Manage master societies, project codes and basic contact information</p>
    </div>
    <a href="<?= base_url('modules/societies/create.php') ?>" class="btn btn-hs-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Add New Society
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, code or location..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/societies/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Society / Project Name</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Phases</th>
                        <th>Blocks</th>
                        <th>Plots</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($societies)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No societies found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($societies as $soc): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?= e($soc['society_code']) ?></span></td>
                                <td class="fw-semibold">
                                    <a href="<?= base_url('modules/societies/view.php?id=' . (int)$soc['id']) ?>" class="text-decoration-none">
                                        <?= e($soc['name']) ?>
                                    </a>
                                </td>
                                <td><?= e($soc['location']) ?></td>
                                <td><?= e($soc['contact_number']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= (int)$soc['phases_count'] ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= (int)$soc['blocks_count'] ?></span></td>
                                <td><span class="badge bg-info bg-opacity-10 text-info fw-bold"><?= (int)$soc['plots_count'] ?></span></td>
                                <td>
                                    <span class="badge <?= ($soc['status'] === 'active') ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst(e($soc['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/societies/edit.php?id=' . (int)$soc['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit Society">
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
