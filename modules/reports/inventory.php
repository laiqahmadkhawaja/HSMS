<?php
/**
 * HSMS - Plot Inventory & Occupancy Report
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('reports_view');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$phaseFilter = (int)($_GET['phase_id'] ?? 0);
$blockFilter = (int)($_GET['block_id'] ?? 0);
$typeFilter = (int)($_GET['plot_type_id'] ?? 0);
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$sql = "
    SELECT p.*, b.block_name, ph.phase_name, pt.name as type_name, pt.code as type_code,
           ps.name as size_name, ps.size_value, ps.unit as size_unit,
           c.full_name as allottee_name, bk.booking_number
    FROM `plots` p
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON p.phase_id = ph.id
    LEFT JOIN `plot_types` pt ON p.plot_type_id = pt.id
    LEFT JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    LEFT JOIN `bookings` bk ON p.id = bk.plot_id AND bk.status = 'active'
    LEFT JOIN `customers` c ON bk.customer_id = c.id
    WHERE p.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (p.plot_number LIKE :s1 OR b.block_name LIKE :s2 OR c.full_name LIKE :s3)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
}
if ($statusFilter !== '') {
    $sql .= " AND p.status = :stat";
    $params['stat'] = $statusFilter;
}
if ($phaseFilter > 0) {
    $sql .= " AND p.phase_id = :phid";
    $params['phid'] = $phaseFilter;
}
if ($blockFilter > 0) {
    $sql .= " AND p.block_id = :blkid";
    $params['blkid'] = $blockFilter;
}
if ($typeFilter > 0) {
    $sql .= " AND p.plot_type_id = :ptid";
    $params['ptid'] = $typeFilter;
}

$sql .= " ORDER BY ph.id ASC, b.id ASC, p.plot_number ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$plots = $stmt->fetchAll();

// Calculate counts & values
$totalUnits = count($plots);
$availCount = 0;
$bookedCount = 0;
$totalInventoryValue = 0.0;

foreach ($plots as $p) {
    $totalInventoryValue += (float)$p['total_price'];
    if ($p['status'] === 'available') {
        $availCount++;
    } elseif (in_array($p['status'], ['booked', 'sold', 'transferred'], true)) {
        $bookedCount++;
    }
}

// CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Plot_Inventory_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Plot Inventory & Occupancy Report', $society['name'] ?? 'Society', 'Exported At:', date('Y-m-d H:i')]);
    fputcsv($out, ['Plot #', 'Block', 'Phase', 'Type', 'Size', 'Base Price', 'Total Price', 'Status', 'Current Allottee', 'Booking #']);

    foreach ($plots as $p) {
        fputcsv($out, [
            $p['plot_number'],
            $p['block_name'],
            $p['phase_name'],
            $p['type_name'],
            $p['size_name'],
            number_format((float)$p['base_price'], 2, '.', ''),
            number_format((float)$p['total_price'], 2, '.', ''),
            ucfirst($p['status']),
            $p['allottee_name'] ?: 'None (Available)',
            $p['booking_number'] ?: '-'
        ]);
    }
    fputcsv($out, ['TOTAL INVENTORY (' . $totalUnits . ' Plots)', '', '', '', '', '', number_format($totalInventoryValue, 2, '.', ''), '', '', '']);
    fclose($out);
    exit;
}

// Fetch phases and types for dropdowns
$phases = $db->prepare("SELECT id, phase_name FROM `phases` WHERE `society_id` = :sid ORDER BY `phase_name` ASC");
$phases->execute(['sid' => $activeSocId]);
$allPhases = $phases->fetchAll();

$plotTypes = $db->query("SELECT id, name FROM `plot_types` WHERE `status` = 'active'");
$allTypes = $plotTypes->fetchAll();

$pageTitle = 'Plot Inventory Report';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Inventory & Occupancy Report</h4>
        <p class="text-muted small mb-0">Master inventory matrix for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('modules/reports/inventory.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Matrix
        </button>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Plots</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= $totalUnits ?> Units</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Available for Sale</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= $availCount ?> Units</div>
            <div class="text-muted small"><?= $totalUnits > 0 ? round(($availCount / $totalUnits) * 100, 1) : 0 ?>% unallocated</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Booked / Sold</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= $bookedCount ?> Units</div>
            <div class="text-muted small"><?= $totalUnits > 0 ? round(($bookedCount / $totalUnits) * 100, 1) : 0 ?>% occupancy</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Inventory Value</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= format_currency($totalInventoryValue) ?></div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Plot #, Block or Allottee..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="available" <?= ($statusFilter === 'available') ? 'selected' : '' ?>>Available</option>
                    <option value="booked" <?= ($statusFilter === 'booked') ? 'selected' : '' ?>>Booked</option>
                    <option value="reserved" <?= ($statusFilter === 'reserved') ? 'selected' : '' ?>>Reserved</option>
                    <option value="sold" <?= ($statusFilter === 'sold') ? 'selected' : '' ?>>Sold</option>
                </select>
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
                <select name="plot_type_id" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <?php foreach ($allTypes as $pt): ?>
                        <option value="<?= (int)$pt['id'] ?>" <?= ($typeFilter === (int)$pt['id']) ? 'selected' : '' ?>><?= e($pt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/reports/inventory.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Plot #</th>
                        <th>Sector / Block</th>
                        <th>Type & Size</th>
                        <th>Features</th>
                        <th class="text-end">Base Price (Rs.)</th>
                        <th class="text-end">Total Price (Rs.)</th>
                        <th>Status</th>
                        <th>Current Allottee / Booking</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plots)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No plots found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($plots as $p): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold fs-6 text-primary"><?= e($p['plot_number']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($p['block_name']) ?></div>
                                    <div class="text-muted small"><?= e($p['phase_name']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($p['size_name'] ?: '5 Marla') ?></div>
                                    <span class="badge bg-light text-muted border"><?= e($p['type_name'] ?: 'Residential') ?></span>
                                </td>
                                <td>
                                    <?php if ($p['is_corner']): ?><span class="badge bg-info text-dark">Corner</span><?php endif; ?>
                                    <?php if ($p['is_boulevard']): ?><span class="badge bg-secondary">Main Blvd</span><?php endif; ?>
                                    <?php if ($p['is_park_facing']): ?><span class="badge bg-success">Park Facing</span><?php endif; ?>
                                    <?php if (!$p['is_corner'] && !$p['is_boulevard'] && !$p['is_park_facing']): ?>
                                        <span class="text-muted small">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= format_currency($p['base_price']) ?></td>
                                <td class="text-end fw-bold"><?= format_currency($p['total_price']) ?></td>
                                <td>
                                    <?php
                                    $pBadge = match ($p['status']) {
                                        'available' => 'bg-success',
                                        'booked'    => 'bg-primary',
                                        'reserved'  => 'bg-warning text-dark',
                                        'sold'      => 'bg-dark',
                                        default     => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $pBadge ?>"><?= ucfirst($p['status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($p['allottee_name'])): ?>
                                        <div class="fw-semibold text-dark"><?= e($p['allottee_name']) ?></div>
                                        <div class="text-muted small">Ref: <?= e($p['booking_number']) ?></div>
                                    <?php else: ?>
                                        <span class="text-success small fw-semibold"><i class="bi bi-check-circle me-1"></i> Available</span>
                                    <?php endif; ?>
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
