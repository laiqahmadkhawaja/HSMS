<?php
/**
 * HSMS - Plot Bookings & Sales Registry
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('bookings_view');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$blockFilter = (int)($_GET['block_id'] ?? 0);

$sql = "
    SELECT bk.*, c.full_name as customer_name, c.cnic_passport, c.mobile,
           p.plot_number, b.block_name, ph.phase_name, pt.name as type_name, ps.name as size_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
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
    $sql .= " AND bk.status = :status";
    $params['status'] = $statusFilter;
}
if ($blockFilter > 0) {
    $sql .= " AND p.block_id = :bid";
    $params['bid'] = $blockFilter;
}

$sql .= " ORDER BY bk.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Fetch Blocks for filter
$stmtBlk = $db->prepare("SELECT id, block_name FROM `blocks` WHERE `society_id` = :sid AND `status` = 'active' ORDER BY `block_name` ASC");
$stmtBlk->execute(['sid' => $activeSocId]);
$allBlocks = $stmtBlk->fetchAll();

$pageTitle = 'Plot Bookings & Sales';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot Bookings & Sales Registry</h4>
        <p class="text-muted small mb-0">Sales contracts and installment accounts for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('modules/bookings/create.php') ?>" class="btn btn-hs-primary btn-sm">
        <i class="bi bi-cart-plus me-1"></i> New Plot Booking
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Booking #, Customer, CNIC or Plot #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
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
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="transferred" <?= ($statusFilter === 'transferred') ? 'selected' : '' ?>>Transferred</option>
                    <option value="cancelled" <?= ($statusFilter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/bookings/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Buyer / Customer</th>
                        <th>Plot Details</th>
                        <th>Net Sale Price</th>
                        <th>Down Payment</th>
                        <th>Total Paid</th>
                        <th>Balance Due</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No plot bookings found matching the filter criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $bk): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$bk['id']) ?>" class="fw-bold text-decoration-none">
                                        <?= e($bk['booking_number']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= format_date($bk['booking_date']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$bk['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($bk['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($bk['cnic_passport']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= e($bk['plot_number']) ?></span>
                                    <span class="badge bg-light text-dark border"><?= e($bk['block_name']) ?></span>
                                    <div class="text-muted mt-1" style="font-size: 0.75rem;"><?= e($bk['size_name']) ?> (<?= e($bk['type_name']) ?>)</div>
                                </td>
                                <td class="fw-bold"><?= format_currency($bk['net_sale_price']) ?></td>
                                <td>
                                    <?= format_currency($bk['down_payment_amount']) ?>
                                    <div><span class="badge bg-light text-secondary border" style="font-size: 0.7rem;"><?= ucfirst(e($bk['down_payment_status'])) ?></span></div>
                                </td>
                                <td class="text-success fw-semibold"><?= format_currency($bk['total_paid']) ?></td>
                                <td class="text-danger fw-bold"><?= format_currency($bk['remaining_balance']) ?></td>
                                <td><span class="status-badge badge-<?= e($bk['status']) ?>"><?= ucfirst(e($bk['status'])) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$bk['id']) ?>" class="btn btn-outline-secondary py-0 px-2" title="View Schedule">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= base_url('modules/bookings/print.php?id=' . (int)$bk['id']) ?>" target="_blank" class="btn btn-outline-secondary py-0 px-2" title="Print Allotment Order">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($bk['status'] === 'active'): ?>
                                            <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$bk['id']) ?>" class="btn btn-success py-0 px-2" title="Collect Payment">
                                                <i class="bi bi-cash"></i>
                                            </a>
                                        <?php endif; ?>
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
