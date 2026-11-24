<?php
/**
 * HSMS - Overdue Aging Analysis Report
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('reports_view');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Aggregate Overdue Installments by Aging Bracket per Active Booking
$sql = "
    SELECT bk.id as booking_id, bk.booking_number, bk.customer_id,
           c.full_name as customer_name, c.cnic_passport, c.mobile, c.whatsapp,
           p.plot_number, b.block_name, ph.phase_name,
           SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 1 AND 30 THEN s.balance_due ELSE 0 END) as age_1_30,
           SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 31 AND 60 THEN s.balance_due ELSE 0 END) as age_31_60,
           SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 61 AND 90 THEN s.balance_due ELSE 0 END) as age_61_90,
           SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) BETWEEN 91 AND 180 THEN s.balance_due ELSE 0 END) as age_91_180,
           SUM(CASE WHEN DATEDIFF(CURDATE(), s.due_date) > 180 THEN s.balance_due ELSE 0 END) as age_180_plus,
           SUM(s.balance_due) as total_overdue,
           COUNT(*) as overdue_installments_count,
           MIN(s.due_date) as oldest_due_date
    FROM `installment_schedules` s
    JOIN `bookings` bk ON s.booking_id = bk.id
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    WHERE bk.society_id = :sid AND bk.status = 'active'
      AND s.status IN ('pending', 'partially_paid') AND s.due_date < CURDATE()
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (bk.booking_number LIKE :s1 OR c.full_name LIKE :s2 OR c.cnic_passport LIKE :s3 OR p.plot_number LIKE :s4)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
}

$sql .= " GROUP BY bk.id, bk.booking_number, bk.customer_id, c.full_name, c.cnic_passport, c.mobile, c.whatsapp, p.plot_number, b.block_name, ph.phase_name";
$sql .= " ORDER BY total_overdue DESC, oldest_due_date ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$agingRows = $stmt->fetchAll();

// Grand Totals across aging brackets
$tot1_30 = 0.0;
$tot31_60 = 0.0;
$tot61_90 = 0.0;
$tot91_180 = 0.0;
$tot180Plus = 0.0;
$grandTotalOverdue = 0.0;

foreach ($agingRows as $r) {
    $tot1_30 += (float)$r['age_1_30'];
    $tot31_60 += (float)$r['age_31_60'];
    $tot61_90 += (float)$r['age_61_90'];
    $tot91_180 += (float)$r['age_91_180'];
    $tot180Plus += (float)$r['age_180_plus'];
    $grandTotalOverdue += (float)$r['total_overdue'];
}

// CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Overdue_Aging_Analysis_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Overdue Aging Analysis Report', $society['name'] ?? 'Society', 'As of Date:', date('Y-m-d')]);
    fputcsv($out, ['Booking #', 'Customer Name', 'CNIC', 'Mobile', 'Plot #', 'Block', '1-30 Days', '31-60 Days', '61-90 Days', '91-180 Days', '180+ Days', 'Total Overdue']);

    foreach ($agingRows as $r) {
        fputcsv($out, [
            $r['booking_number'],
            $r['customer_name'],
            $r['cnic_passport'],
            $r['mobile'],
            $r['plot_number'],
            $r['block_name'],
            number_format((float)$r['age_1_30'], 2, '.', ''),
            number_format((float)$r['age_31_60'], 2, '.', ''),
            number_format((float)$r['age_61_90'], 2, '.', ''),
            number_format((float)$r['age_91_180'], 2, '.', ''),
            number_format((float)$r['age_180_plus'], 2, '.', ''),
            number_format((float)$r['total_overdue'], 2, '.', '')
        ]);
    }
    fputcsv($out, ['TOTALS (' . count($agingRows) . ' Accounts)', '', '', '', '', '', number_format($tot1_30, 2, '.', ''), number_format($tot31_60, 2, '.', ''), number_format($tot61_90, 2, '.', ''), number_format($tot91_180, 2, '.', ''), number_format($tot180Plus, 2, '.', ''), number_format($grandTotalOverdue, 2, '.', '')]);
    fclose($out);
    exit;
}

$pageTitle = 'Overdue Aging Analysis';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Overdue Aging Analysis Report</h4>
        <p class="text-muted small mb-0">Arrears maturity schedule & recovery brackets for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('modules/reports/aging.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Bracket Summary Cards -->
<div class="row g-2 mb-4">
    <div class="col">
        <div class="card-hs p-2 text-center">
            <div class="text-muted small fw-semibold">1 - 30 Days</div>
            <div class="fs-6 fw-bold text-dark mt-1"><?= format_currency($tot1_30) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card-hs p-2 text-center">
            <div class="text-muted small fw-semibold">31 - 60 Days</div>
            <div class="fs-6 fw-bold text-warning mt-1"><?= format_currency($tot31_60) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card-hs p-2 text-center">
            <div class="text-muted small fw-semibold">61 - 90 Days</div>
            <div class="fs-6 fw-bold text-danger mt-1"><?= format_currency($tot61_90) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card-hs p-2 text-center">
            <div class="text-muted small fw-semibold">91 - 180 Days</div>
            <div class="fs-6 fw-bold text-danger mt-1"><?= format_currency($tot91_180) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card-hs p-2 text-center">
            <div class="text-muted small fw-semibold">180+ Days (Critical)</div>
            <div class="fs-6 fw-bold text-danger mt-1"><?= format_currency($tot180Plus) ?></div>
        </div>
    </div>
    <div class="col">
        <div class="card-hs p-2 text-center bg-danger bg-opacity-10 border-danger">
            <div class="text-danger small fw-bold">Total Arrears</div>
            <div class="fs-6 fw-bold text-danger mt-1"><?= format_currency($grandTotalOverdue) ?></div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Buyer Name, CNIC, Plot # or Booking #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Filter Accounts</button>
            </div>
            <div class="col-md-3">
                <a href="<?= base_url('modules/reports/aging.php') ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Booking / Plot</th>
                        <th>Buyer Particulars</th>
                        <th class="text-end">1 - 30 Days</th>
                        <th class="text-end">31 - 60 Days</th>
                        <th class="text-end">61 - 90 Days</th>
                        <th class="text-end">91 - 180 Days</th>
                        <th class="text-end">180+ Days</th>
                        <th class="text-end">Total Overdue (Rs.)</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agingRows)): ?>
                        <tr><td colspan="9" class="text-center text-success py-4"><i class="bi bi-check-circle me-1"></i> Excellent! No overdue accounts matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($agingRows as $r): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$r['booking_id']) ?>" class="fw-bold font-monospace text-decoration-none">
                                        <?= e($r['booking_number']) ?>
                                    </a>
                                    <div>
                                        <span class="badge bg-primary"><?= e($r['plot_number']) ?></span>
                                        <span class="badge bg-light text-dark border"><?= e($r['block_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$r['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($r['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <?= e($r['mobile']) ?>
                                        <?php if (!empty($r['whatsapp'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $r['whatsapp']) ?>?text=Dear%20Customer,%20reminder%20regarding%20overdue%20balance%20of%20Rs.%20<?= number_format((float)$r['total_overdue'], 2) ?>%20on%20Plot%20<?= urlencode($r['plot_number']) ?>." target="_blank" class="text-success ms-1">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end text-muted"><?= ((float)$r['age_1_30'] > 0) ? format_currency($r['age_1_30']) : '-' ?></td>
                                <td class="text-end <?= ((float)$r['age_31_60'] > 0) ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= ((float)$r['age_31_60'] > 0) ? format_currency($r['age_31_60']) : '-' ?></td>
                                <td class="text-end <?= ((float)$r['age_61_90'] > 0) ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= ((float)$r['age_61_90'] > 0) ? format_currency($r['age_61_90']) : '-' ?></td>
                                <td class="text-end <?= ((float)$r['age_91_180'] > 0) ? 'text-danger fw-bold' : 'text-muted' ?>"><?= ((float)$r['age_91_180'] > 0) ? format_currency($r['age_91_180']) : '-' ?></td>
                                <td class="text-end <?= ((float)$r['age_180_plus'] > 0) ? 'text-danger fw-bold fs-6' : 'text-muted' ?>"><?= ((float)$r['age_180_plus'] > 0) ? format_currency($r['age_180_plus']) : '-' ?></td>
                                <td class="text-end fw-bold fs-6 text-danger"><?= format_currency($r['total_overdue']) ?></td>
                                <td class="text-end no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/recoveries/promises.php?booking_id=' . (int)$r['booking_id']) ?>" class="btn btn-outline-warning py-0 px-2" title="Log Promise to Pay">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$r['booking_id']) ?>" class="btn btn-success py-0 px-2" title="Collect Payment">
                                            <i class="bi bi-cash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($agingRows)): ?>
                    <tfoot class="table-light fw-bold fs-6">
                        <tr>
                            <td colspan="2" class="text-end text-uppercase">Arrears Totals:</td>
                            <td class="text-end"><?= format_currency($tot1_30) ?></td>
                            <td class="text-end text-warning"><?= format_currency($tot31_60) ?></td>
                            <td class="text-end text-danger"><?= format_currency($tot61_90) ?></td>
                            <td class="text-end text-danger"><?= format_currency($tot91_180) ?></td>
                            <td class="text-end text-danger"><?= format_currency($tot180Plus) ?></td>
                            <td class="text-end text-danger fs-6"><?= format_currency($grandTotalOverdue) ?></td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
