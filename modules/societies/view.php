<?php
/**
 * HSMS - Society / Project Details & Structure View
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('societies_manage');

$db = get_db_connection();
$socId = (int)($_GET['id'] ?? 0);

if ($socId <= 0) {
    redirect_with_message(base_url('modules/societies/index.php'), 'Invalid society ID specified.', 'danger');
}

$stmt = $db->prepare("SELECT * FROM `societies` WHERE `id` = :id");
$stmt->execute(['id' => $socId]);
$soc = $stmt->fetch();

if (!$soc) {
    redirect_with_message(base_url('modules/societies/index.php'), 'Society record not found.', 'danger');
}

// Fetch Phases
$stmtPh = $db->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM `blocks` WHERE `phase_id` = p.id) as blocks_count,
           (SELECT COUNT(*) FROM `plots` WHERE `phase_id` = p.id) as plots_count
    FROM `phases` p
    WHERE p.society_id = :sid
    ORDER BY p.id ASC
");
$stmtPh->execute(['sid' => $socId]);
$phases = $stmtPh->fetchAll();

// Fetch Blocks
$stmtBlk = $db->prepare("
    SELECT b.*, ph.phase_name,
           (SELECT COUNT(*) FROM `plots` WHERE `block_id` = b.id) as plots_count,
           (SELECT COUNT(*) FROM `plots` WHERE `block_id` = b.id AND `status` = 'available') as available_count,
           (SELECT COUNT(*) FROM `plots` WHERE `block_id` = b.id AND `status` = 'booked') as booked_count
    FROM `blocks` b
    JOIN `phases` ph ON b.phase_id = ph.id
    WHERE b.society_id = :sid
    ORDER BY b.id ASC
");
$stmtBlk->execute(['sid' => $socId]);
$blocks = $stmtBlk->fetchAll();

// Plot Status Counts
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) as total_plots,
        COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) as available_plots,
        COALESCE(SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END), 0) as booked_plots,
        COALESCE(SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END), 0) as sold_plots,
        COALESCE(SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END), 0) as reserved_plots
    FROM `plots`
    WHERE `society_id` = :sid
");
$stmtStats->execute(['sid' => $socId]);
$stats = $stmtStats->fetch();

$pageTitle = $soc['name'] . ' — Project Details';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><?= e($soc['name']) ?></h4>
        <p class="text-muted small mb-0">Project Code: <strong><?= e($soc['society_code']) ?></strong> | Location: <?= e($soc['location']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/societies/edit.php?id=' . (int)$soc['id']) ?>" class="btn btn-hs-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i> Edit Project
        </a>
        <a href="<?= base_url('switch_society.php?id=' . (int)$soc['id'] . '&csrf=' . csrf_token()) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i> Switch Active Context
        </a>
        <a href="<?= base_url('modules/societies/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> All Societies
        </a>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="p-3 bg-white rounded border shadow-sm">
            <div class="text-muted small">Total Phases</div>
            <div class="fs-4 fw-bold text-dark"><?= count($phases) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white rounded border shadow-sm">
            <div class="text-muted small">Total Blocks</div>
            <div class="fs-4 fw-bold text-dark"><?= count($blocks) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white rounded border shadow-sm">
            <div class="text-muted small">Total Plots</div>
            <div class="fs-4 fw-bold text-primary"><?= (int)$stats['total_plots'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white rounded border shadow-sm">
            <div class="text-muted small">Available Inventory</div>
            <div class="fs-4 fw-bold text-success"><?= (int)$stats['available_plots'] ?> Plots</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Society Information -->
    <div class="col-lg-5">
        <div class="card-hs h-100">
            <div class="card-header bg-dark text-white">
                <span class="fw-bold"><i class="bi bi-info-circle me-1"></i> Project Master Details</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Society Code:</span>
                        <span class="font-monospace fw-bold text-primary"><?= e($soc['society_code']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-<?= ($soc['status'] === 'active') ? 'success' : 'secondary' ?>"><?= ucfirst(e($soc['status'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Registration / NOC:</span>
                        <span class="fw-semibold"><?= e($soc['registration_number'] ?: 'N/A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Contact Phone:</span>
                        <span><?= e($soc['contact_number'] ?: 'N/A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Email:</span>
                        <span><?= e($soc['email'] ?: 'N/A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Physical Address:</span>
                        <span class="text-end" style="max-width: 250px;"><?= e($soc['address'] ?: 'N/A') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Start Date:</span>
                        <span><?= format_date($soc['start_date']) ?></span>
                    </li>
                </ul>

                <?php if (!empty($soc['remarks'])): ?>
                    <div class="mt-3 p-2 bg-light rounded border small">
                        <strong>Project Remarks:</strong>
                        <p class="text-muted mb-0 mt-1"><?= nl2br(e($soc['remarks'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Phases Breakdown -->
    <div class="col-lg-7">
        <div class="card-hs h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i> Phases in Project</span>
                <a href="<?= base_url('modules/phases/index.php') ?>" class="btn btn-outline-primary btn-sm py-0">Manage Phases</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hs align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Phase Name</th>
                                <th>Blocks</th>
                                <th>Plots</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($phases)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No phases created yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($phases as $ph): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border font-monospace"><?= e($ph['phase_code']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= e($ph['phase_name']) ?></td>
                                        <td><?= (int)$ph['blocks_count'] ?> Blocks</td>
                                        <td><?= (int)$ph['plots_count'] ?> Plots</td>
                                        <td><span class="badge bg-<?= ($ph['status'] === 'active') ? 'success' : 'secondary' ?>"><?= ucfirst(e($ph['status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Blocks & Sector Directory -->
<div class="card-hs">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-grid-3x3 text-primary me-2"></i> Blocks & Sector Allocation Matrix</span>
        <a href="<?= base_url('modules/blocks/index.php') ?>" class="btn btn-outline-primary btn-sm py-0">Manage Blocks</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th>Block Code</th>
                        <th>Block Name</th>
                        <th>Parent Phase</th>
                        <th>Total Plots</th>
                        <th>Available</th>
                        <th>Booked</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocks)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No blocks allocated in this society.</td></tr>
                    <?php else: ?>
                        <?php foreach ($blocks as $blk): ?>
                            <tr>
                                <td><span class="badge bg-secondary font-monospace"><?= e($blk['block_code']) ?></span></td>
                                <td class="fw-semibold text-dark"><?= e($blk['block_name']) ?></td>
                                <td class="text-muted small"><?= e($blk['phase_name']) ?></td>
                                <td><strong><?= (int)$blk['plots_count'] ?></strong></td>
                                <td><span class="badge bg-success"><?= (int)$blk['available_count'] ?> Available</span></td>
                                <td><span class="badge bg-primary"><?= (int)$blk['booked_count'] ?> Booked</span></td>
                                <td><span class="badge bg-<?= ($blk['status'] === 'active') ? 'success' : 'secondary' ?>"><?= ucfirst(e($blk['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>