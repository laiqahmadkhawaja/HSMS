<?php
/**
 * HSMS - Persistent Defaulters & Recovery Notices Report
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('reports_view');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$minOverdueCount = max(1, (int)($_GET['min_dues'] ?? 2));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Query Defaulters with multiple overdue installments
$sql = "
    SELECT bk.id as booking_id, bk.booking_number, bk.customer_id, bk.remaining_balance,
           c.full_name as customer_name, c.cnic_passport, c.mobile, c.whatsapp, c.city,
           p.plot_number, b.block_name, ph.phase_name,
           COUNT(s.id) as overdue_count,
           SUM(s.balance_due) as total_overdue_amount,
           MIN(s.due_date) as oldest_overdue_date,
           MAX(s.due_date) as latest_overdue_date,
           (
               SELECT p2.payment_date 
               FROM `payments` p2 
               WHERE p2.booking_id = bk.id AND p2.status = 'posted' 
               ORDER BY p2.id DESC LIMIT 1
           ) as last_payment_date
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    JOIN `installment_schedules` s ON bk.id = s.booking_id
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

$sql .= " GROUP BY bk.id, bk.booking_number, bk.customer_id, bk.remaining_balance, c.full_name, c.cnic_passport, c.mobile, c.whatsapp, c.city, p.plot_number, b.block_name, ph.phase_name";
$sql .= " HAVING overdue_count >= :min_dues";
$params['min_dues'] = $minOverdueCount;

$sql .= " ORDER BY total_overdue_amount DESC, overdue_count DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$defaulters = $stmt->fetchAll();

// Totals
$totDefaulters = count($defaulters);
$totOverdueSum = 0.0;
$totRemainingSum = 0.0;

foreach ($defaulters as $d) {
    $totOverdueSum += (float)$d['total_overdue_amount'];
    $totRemainingSum += (float)$d['remaining_balance'];
}

// CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Defaulters_Register_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Defaulters & Arrears Register', $society['name'] ?? 'Society', 'Generated At:', date('Y-m-d H:i')]);
    fputcsv($out, ['Booking #', 'Customer Name', 'CNIC', 'Mobile', 'Plot #', 'Block', 'Overdue Dues', 'Total Overdue (Rs.)', 'Remaining Bal (Rs.)', 'Oldest Due Date', 'Last Paid Date']);

    foreach ($defaulters as $d) {
        fputcsv($out, [
            $d['booking_number'],
            $d['customer_name'],
            $d['cnic_passport'],
            $d['mobile'],
            $d['plot_number'],
            $d['block_name'],
            $d['overdue_count'] . ' Installments',
            number_format((float)$d['total_overdue_amount'], 2, '.', ''),
            number_format((float)$d['remaining_balance'], 2, '.', ''),
            $d['oldest_overdue_date'],
            $d['last_payment_date'] ?: 'Never'
        ]);
    }
    fputcsv($out, ['TOTALS (' . $totDefaulters . ' Defaulters)', '', '', '', '', '', '', number_format($totOverdueSum, 2, '.', ''), number_format($totRemainingSum, 2, '.', ''), '', '']);
    fclose($out);
    exit;
}

$pageTitle = 'Defaulters Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Persistent Defaulters Register</h4>
        <p class="text-muted small mb-0">High-risk overdue arrears and collection notices for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('modules/reports/defaulters.php?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Register
        </button>
    </div>
</div>

<!-- Defaulter Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Persistent Defaulters</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= $totDefaulters ?> Accounts</div>
            <div class="text-muted small">Holding &ge; <?= $minOverdueCount ?> overdue installments</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Overdue Arrears</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= format_currency($totOverdueSum) ?></div>
            <div class="text-muted small">Immediate cash recovery target</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">At-Risk Contract Value</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= format_currency($totRemainingSum) ?></div>
            <div class="text-muted small">Outstanding balance on defaulter plots</div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Buyer Name, CNIC, Plot # or Booking #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="min_dues" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="1" <?= ($minOverdueCount === 1) ? 'selected' : '' ?>>1+ Overdue Installments (All Overdue)</option>
                    <option value="2" <?= ($minOverdueCount === 2) ? 'selected' : '' ?>>2+ Overdue Installments (Standard Defaulters)</option>
                    <option value="3" <?= ($minOverdueCount === 3) ? 'selected' : '' ?>>3+ Overdue Installments (Chronic Default)</option>
                    <option value="5" <?= ($minOverdueCount === 5) ? 'selected' : '' ?>>5+ Overdue Installments (Final Notice)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('modules/reports/defaulters.php') ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Booking / Plot</th>
                        <th>Buyer Particulars</th>
                        <th>Overdue Dues</th>
                        <th>Oldest Default Date</th>
                        <th class="text-end">Overdue Arrears (Rs.)</th>
                        <th class="text-end">Contract Balance (Rs.)</th>
                        <th>Last Payment</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($defaulters)): ?>
                        <tr><td colspan="8" class="text-center text-success py-4"><i class="bi bi-shield-check fs-5 me-1"></i> No accounts matching defaulter threshold.</td></tr>
                    <?php else: ?>
                        <?php foreach ($defaulters as $d): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$d['booking_id']) ?>" class="fw-bold font-monospace text-danger text-decoration-none">
                                        <?= e($d['booking_number']) ?>
                                    </a>
                                    <div>
                                        <span class="badge bg-secondary"><?= e($d['plot_number']) ?></span>
                                        <span class="badge bg-light text-dark border"><?= e($d['block_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$d['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($d['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">CNIC: <?= e($d['cnic_passport']) ?> | <?= e($d['mobile']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-danger fs-6"><?= (int)$d['overdue_count'] ?> Overdue</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-danger"><?= date('d M Y', strtotime($d['oldest_overdue_date'])) ?></div>
                                    <div class="text-muted small"><?= (int)((strtotime('now') - strtotime($d['oldest_overdue_date'])) / 86400) ?> days overdue</div>
                                </td>
                                <td class="text-end fw-bold fs-6 text-danger">
                                    <?= format_currency($d['total_overdue_amount']) ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= format_currency($d['remaining_balance']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= $d['last_payment_date'] ? date('d M Y', strtotime($d['last_payment_date'])) : '<span class="text-danger fw-semibold">No Payments</span>' ?>
                                </td>
                                <td class="text-end no-print">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!empty($d['whatsapp'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $d['whatsapp']) ?>?text=FINAL%20OVERDUE%20NOTICE:%20Dear%20<?= urlencode($d['customer_name']) ?>,%20you%20have%20<?= (int)$d['overdue_count'] ?>%20unpaid%20installments%20totaling%20Rs.%20<?= number_format((float)$d['total_overdue_amount'], 2) ?>%20on%20Plot%20<?= urlencode($d['plot_number']) ?>.%20Please%20settle%20immediately." target="_blank" class="btn btn-outline-success" title="Send WhatsApp Demand Notice">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= base_url('modules/recoveries/promises.php?booking_id=' . (int)$d['booking_id']) ?>" class="btn btn-outline-warning" title="Log Promise to Pay">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$d['booking_id']) ?>" class="btn btn-success" title="Collect Payment">
                                            <i class="bi bi-cash"></i>
                                        </a>
                                        <a href="<?= base_url('modules/cancellations/create.php?booking_id=' . (int)$d['booking_id']) ?>" class="btn btn-outline-danger" title="Initiate Plot Cancellation">
                                            <i class="bi bi-x-octagon"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($defaulters)): ?>
                    <tfoot class="table-light fw-bold fs-6">
                        <tr>
                            <td colspan="4" class="text-end text-uppercase">Arrears Total:</td>
                            <td class="text-end text-danger"><?= format_currency($totOverdueSum) ?></td>
                            <td class="text-end"><?= format_currency($totRemainingSum) ?></td>
                            <td colspan="2" class="no-print"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
