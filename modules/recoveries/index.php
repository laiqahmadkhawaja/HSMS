<?php
/**
 * HSMS - Recovery Desk & Collection Operations
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('payments_collect');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$overdueOnly = isset($_GET['overdue_only']) && $_GET['overdue_only'] === '1';

$sql = "
    SELECT bk.*, c.full_name as customer_name, c.customer_id_code, c.cnic_passport, c.mobile, c.whatsapp,
           p.plot_number, b.block_name, ph.phase_name,
           (SELECT COALESCE(SUM(balance_due), 0) FROM `installment_schedules` WHERE `booking_id` = bk.id AND `status` IN ('pending', 'partially_paid') AND `due_date` < CURDATE()) as overdue_amount,
           (SELECT COUNT(*) FROM `installment_schedules` WHERE `booking_id` = bk.id AND `status` IN ('pending', 'partially_paid') AND `due_date` < CURDATE()) as overdue_count
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON p.phase_id = ph.id
    WHERE bk.society_id = :sid AND bk.status = 'active'
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (c.full_name LIKE :s1 OR c.cnic_passport LIKE :s2 OR c.mobile LIKE :s3 OR p.plot_number LIKE :s4 OR bk.booking_number LIKE :s5)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
    $params['s5'] = "%{$search}%";
}

if ($overdueOnly) {
    $sql .= " HAVING overdue_amount > 0";
}

$sql .= " ORDER BY overdue_amount DESC, bk.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$pageTitle = 'Recovery Desk';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Recovery Desk & Collections</h4>
        <p class="text-muted small mb-0">Active customer recovery accounts for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/recoveries/promises.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i> Promises to Pay
        </a>
        <a href="<?= base_url('modules/receipts/index.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-receipt me-1"></i> Receipts Register
        </a>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Buyer Name, CNIC, Mobile, Plot # or Booking #..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="overdue_only" id="overdue_only" value="1" <?= $overdueOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label small fw-bold text-danger" for="overdue_only">
                        Show Defaulters / Overdue Only
                    </label>
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/recoveries/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Booking / Plot</th>
                        <th>Buyer Particulars</th>
                        <th>Sale Price</th>
                        <th>Total Paid</th>
                        <th>Remaining Bal</th>
                        <th>Overdue Amount</th>
                        <th>Overdue Dues</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accounts)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No recovery accounts found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$acc['id']) ?>" class="fw-bold text-decoration-none">
                                        <?= e($acc['booking_number']) ?>
                                    </a>
                                    <div>
                                        <span class="badge bg-primary"><?= e($acc['plot_number']) ?></span>
                                        <span class="badge bg-light text-dark border"><?= e($acc['block_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$acc['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($acc['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small"><?= e($acc['mobile']) ?> | <?= e($acc['cnic_passport']) ?></div>
                                </td>
                                <td><?= format_currency($acc['net_sale_price']) ?></td>
                                <td class="text-success fw-semibold"><?= format_currency($acc['total_paid']) ?></td>
                                <td class="fw-semibold"><?= format_currency($acc['remaining_balance']) ?></td>
                                <td class="fw-bold <?= ((float)$acc['overdue_amount'] > 0) ? 'text-danger' : 'text-success' ?>">
                                    <?= ((float)$acc['overdue_amount'] > 0) ? format_currency($acc['overdue_amount']) : 'Nil' ?>
                                </td>
                                <td>
                                    <?php if ((int)$acc['overdue_count'] > 0): ?>
                                        <span class="badge bg-danger"><?= (int)$acc['overdue_count'] ?> Due</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Up to date</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/recoveries/promises.php?booking_id=' . (int)$acc['id']) ?>" class="btn btn-outline-warning py-1 px-2" title="Log Promise to Pay">
                                            <i class="bi bi-clock-history"></i> PTP
                                        </a>
                                        <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$acc['id']) ?>" class="btn btn-success py-1 px-3 fw-semibold">
                                            <i class="bi bi-cash me-1"></i> Collect
                                        </a>
                                    </div>
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
