<?php
/**
 * HSMS - Sales Register Report
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
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$sql = "
    SELECT bk.*, c.full_name as customer_name, c.cnic_passport, c.mobile, c.city,
           p.plot_number, b.block_name, ph.phase_name,
           u.name as sales_officer_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    LEFT JOIN `users` u ON bk.sales_officer_id = u.id
    WHERE bk.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (bk.booking_number LIKE :s1 OR c.full_name LIKE :s2 OR c.cnic_passport LIKE :s3 OR p.plot_number LIKE :s4)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
}
if ($statusFilter !== '') {
    $sql .= " AND bk.status = :stat";
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
if ($startDate !== '') {
    $sql .= " AND bk.booking_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND bk.booking_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY bk.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Calculate totals
$totGross = 0.0;
$totDp = 0.0;
$totPaid = 0.0;
$totBal = 0.0;

foreach ($bookings as $b) {
    $totGross += (float)$b['net_sale_price'];
    $totDp += (float)$b['down_payment_amount'];
    $totPaid += (float)$b['total_paid'];
    $totBal += (float)$b['remaining_balance'];
}

// CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Sales_Register_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sales Register Report', $society['name'] ?? 'Society', 'Exported At:', date('Y-m-d H:i')]);
    fputcsv($out, ['Booking #', 'Booking Date', 'Customer Name', 'CNIC', 'Mobile', 'Plot #', 'Block', 'Phase', 'Gross Sale Price', 'Down Payment', 'Total Paid', 'Remaining Bal', 'Status']);

    foreach ($bookings as $b) {
        fputcsv($out, [
            $b['booking_number'],
            $b['booking_date'],
            $b['customer_name'],
            $b['cnic_passport'],
            $b['mobile'],
            $b['plot_number'],
            $b['block_name'],
            $b['phase_name'],
            number_format((float)$b['net_sale_price'], 2, '.', ''),
            number_format((float)$b['down_payment_amount'], 2, '.', ''),
            number_format((float)$b['total_paid'], 2, '.', ''),
            number_format((float)$b['remaining_balance'], 2, '.', ''),
            ucfirst($b['status'])
        ]);
    }
    fputcsv($out, ['TOTALS (' . count($bookings) . ' Contracts)', '', '', '', '', '', '', '', number_format($totGross, 2, '.', ''), number_format($totDp, 2, '.', ''), number_format($totPaid, 2, '.', ''), number_format($totBal, 2, '.', ''), '']);
    fclose($out);
    exit;
}

// Fetch phases and blocks for filters
$phases = $db->prepare("SELECT id, phase_name FROM `phases` WHERE `society_id` = :sid ORDER BY `phase_name` ASC");
$phases->execute(['sid' => $activeSocId]);
$allPhases = $phases->fetchAll();

$pageTitle = 'Sales Register Report';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Sales Register</h4>
        <p class="text-muted small mb-0">Contract sales audit and receivable ledger for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('modules/reports/sales_register.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Register
        </button>
    </div>
</div>

<!-- Summary Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Contracts</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= count($bookings) ?> Units</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Gross Sales Value</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= format_currency($totGross) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Collections Realized</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= format_currency($totPaid) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Outstanding Receivables</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= format_currency($totBal) ?></div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Booking #, Buyer, CNIC, Plot..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="transferred" <?= ($statusFilter === 'transferred') ? 'selected' : '' ?>>Transferred</option>
                    <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
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
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="Start Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" placeholder="End Date">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Booking # / Date</th>
                        <th>Buyer Particulars</th>
                        <th>Plot / Sector</th>
                        <th class="text-end">Sale Price (Rs.)</th>
                        <th class="text-end">Paid (Rs.)</th>
                        <th class="text-end">Balance Due (Rs.)</th>
                        <th>Status</th>
                        <th class="text-end no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No booking sales found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$b['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                        <?= e($b['booking_number']) ?>
                                    </a>
                                    <div class="text-muted small"><?= date('d M Y', strtotime($b['booking_date'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$b['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($b['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">CNIC: <?= e($b['cnic_passport']) ?> | <?= e($b['mobile']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= e($b['plot_number']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= e($b['block_name']) ?></span>
                                    <div class="text-muted small"><?= e($b['phase_name']) ?></div>
                                </td>
                                <td class="text-end fw-semibold"><?= format_currency($b['net_sale_price']) ?></td>
                                <td class="text-end fw-semibold text-success"><?= format_currency($b['total_paid']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($b['remaining_balance']) ?></td>
                                <td>
                                    <?php
                                    $stClass = match ($b['status']) {
                                        'active'      => 'bg-success',
                                        'cancelled'   => 'bg-danger',
                                        'transferred' => 'bg-info text-dark',
                                        default       => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $stClass ?>"><?= ucfirst($b['status']) ?></span>
                                </td>
                                <td class="text-end no-print">
                                    <a href="<?= base_url('modules/bookings/print.php?id=' . (int)$b['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Print Allotment Certificate">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($bookings)): ?>
                    <tfoot class="table-light fw-bold fs-6">
                        <tr>
                            <td colspan="3" class="text-end text-uppercase">Summary Totals:</td>
                            <td class="text-end text-primary"><?= format_currency($totGross) ?></td>
                            <td class="text-end text-success"><?= format_currency($totPaid) ?></td>
                            <td class="text-end text-danger"><?= format_currency($totBal) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
