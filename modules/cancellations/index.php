<?php
/**
 * HSMS - Plot Cancellations Master Register
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('bookings_cancel');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$refundStatus = trim($_GET['refund_status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT cnl.*, bk.booking_number, p.plot_number, b.block_name, ph.phase_name,
           c.full_name as customer_name, c.cnic_passport, c.mobile,
           u.name as approved_by_name
    FROM `cancellations` cnl
    JOIN `bookings` bk ON cnl.booking_id = bk.id
    JOIN `plots` p ON cnl.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    JOIN `customers` c ON cnl.customer_id = c.id
    JOIN `users` u ON cnl.approved_by = u.id
    WHERE bk.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (cnl.cancellation_number LIKE :s1 OR c.full_name LIKE :s2 OR c.cnic_passport LIKE :s3 OR p.plot_number LIKE :s4 OR bk.booking_number LIKE :s5)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
    $params['s5'] = "%{$search}%";
}
if ($refundStatus !== '') {
    $sql .= " AND cnl.refund_status = :rstat";
    $params['rstat'] = $refundStatus;
}
if ($startDate !== '') {
    $sql .= " AND cnl.cancellation_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND cnl.cancellation_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY cnl.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cancellations = $stmt->fetchAll();

$pageTitle = 'Plot Cancellations Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Cancellations & Refunds</h4>
        <p class="text-muted small mb-0">Cancelled contracts, policy deductions and refund liabilities for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('modules/cancellations/create.php') ?>" class="btn btn-danger btn-sm">
        <i class="bi bi-x-circle me-1"></i> New Plot Cancellation
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Cancellation #, Buyer, CNIC, Plot # or Booking #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="refund_status" class="form-select form-select-sm">
                    <option value="">All Refund Statuses</option>
                    <option value="pending" <?= ($refundStatus === 'pending') ? 'selected' : '' ?>>Pending Refund</option>
                    <option value="partially_refunded" <?= ($refundStatus === 'partially_refunded') ? 'selected' : '' ?>>Partially Refunded</option>
                    <option value="fully_refunded" <?= ($refundStatus === 'fully_refunded') ? 'selected' : '' ?>>Fully Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="Start Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/cancellations/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Cancellation # / Date</th>
                        <th>Plot / Sector</th>
                        <th>Customer</th>
                        <th>Total Paid at Cancel</th>
                        <th>Deduction Retained</th>
                        <th>Refund Payable</th>
                        <th>Refund Status</th>
                        <th>Reason</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cancellations)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No plot cancellations recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cancellations as $cnl): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-danger"><?= e($cnl['cancellation_number']) ?></div>
                                    <div class="text-muted small"><?= date('d M Y', strtotime($cnl['cancellation_date'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($cnl['plot_number']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= e($cnl['block_name']) ?></span>
                                    <div class="text-muted small">Ref: <?= e($cnl['booking_number']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($cnl['customer_name']) ?></div>
                                    <div class="text-muted small">CNIC: <?= e($cnl['cnic_passport']) ?></div>
                                </td>
                                <td class="fw-semibold"><?= format_currency($cnl['total_paid_at_cancel']) ?></td>
                                <td class="text-danger fw-semibold"><?= format_currency($cnl['deduction_amount']) ?></td>
                                <td class="fw-bold text-primary"><?= format_currency($cnl['refund_amount_payable']) ?></td>
                                <td>
                                    <?php
                                    $rBadge = match ($cnl['refund_status']) {
                                        'pending'            => 'bg-warning text-dark',
                                        'partially_refunded' => 'bg-info text-dark',
                                        'fully_refunded'     => 'bg-success',
                                        default              => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $rBadge ?>"><?= ucfirst(str_replace('_', ' ', $cnl['refund_status'])) ?></span>
                                </td>
                                <td class="small text-muted" style="max-width: 180px;">
                                    <?= e($cnl['reason']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/cancellations/print.php?id=' . (int)$cnl['id']) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Print Cancellation Order">
                                        <i class="bi bi-printer me-1"></i> Voucher
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
