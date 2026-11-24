<?php
/**
 * HSMS - Plot Transfers Master Register
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('transfers_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT trf.*, p.plot_number, b.block_name, ph.phase_name,
           seller.full_name as seller_name, seller.cnic_passport as seller_cnic, seller.mobile as seller_mobile,
           buyer.full_name as buyer_name, buyer.cnic_passport as buyer_cnic, buyer.mobile as buyer_mobile,
           bk.booking_number, u.name as approved_by_name
    FROM `plot_transfers` trf
    JOIN `plots` p ON trf.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    JOIN `bookings` bk ON trf.booking_id = bk.id
    JOIN `customers` seller ON trf.from_customer_id = seller.id
    JOIN `customers` buyer ON trf.to_customer_id = buyer.id
    LEFT JOIN `users` u ON trf.approved_by = u.id
    WHERE trf.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (trf.transfer_number LIKE :s1 OR seller.full_name LIKE :s2 OR buyer.full_name LIKE :s3 OR p.plot_number LIKE :s4 OR bk.booking_number LIKE :s5)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
    $params['s5'] = "%{$search}%";
}
if ($startDate !== '') {
    $sql .= " AND trf.transfer_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND trf.transfer_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY trf.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

$pageTitle = 'Plot Transfers Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Ownership Transfers</h4>
        <p class="text-muted small mb-0">Legal ownership transfers and balance takeover register for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('modules/transfers/create.php') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-arrow-left-right me-1"></i> New Plot Transfer
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Transfer #, Buyer, Seller, Plot # or Booking #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="Start Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" placeholder="End Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/transfers/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Transfer # / Date</th>
                        <th>Plot / Sector</th>
                        <th>Seller (Previous Owner)</th>
                        <th>Buyer (New Allottee)</th>
                        <th>Balance Taken Over</th>
                        <th>Transfer Fee</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No plot transfers recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $trf): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary"><?= e($trf['transfer_number']) ?></div>
                                    <div class="text-muted small"><?= date('d M Y', strtotime($trf['transfer_date'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= e($trf['plot_number']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= e($trf['block_name']) ?></span>
                                    <div class="text-muted small">Ref: <?= e($trf['booking_number']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-danger"><?= e($trf['seller_name']) ?></div>
                                    <div class="text-muted small">CNIC: <?= e($trf['seller_cnic']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-success"><?= e($trf['buyer_name']) ?></div>
                                    <div class="text-muted small">CNIC: <?= e($trf['buyer_cnic']) ?></div>
                                </td>
                                <td class="fw-semibold">
                                    <?= format_currency($trf['pending_balance_transferred']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= format_currency($trf['transfer_fee']) ?></div>
                                    <span class="badge <?= ($trf['transfer_fee_status'] === 'paid') ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= ucfirst($trf['transfer_fee_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">Approved</span>
                                    <div class="text-muted" style="font-size: 0.72rem;">By <?= e($trf['approved_by_name'] ?? 'Admin') ?></div>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/transfers/print.php?id=' . (int)$trf['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Transfer Certificate">
                                        <i class="bi bi-printer me-1"></i> Certificate
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
