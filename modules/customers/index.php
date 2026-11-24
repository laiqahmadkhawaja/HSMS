<?php
/**
 * HSMS - Customer Master Registry
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('customers_manage');

$db = get_db_connection();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT c.*,
        (SELECT COUNT(*) FROM `bookings` WHERE `customer_id` = c.id AND `status` = 'active') as active_plots_count,
        (SELECT COALESCE(SUM(remaining_balance), 0) FROM `bookings` WHERE `customer_id` = c.id AND `status` = 'active') as total_outstanding,
        (SELECT COALESCE(SUM(advance_balance), 0) FROM `bookings` WHERE `customer_id` = c.id AND `status` = 'active') as total_advance
    FROM `customers` c
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.full_name LIKE :search OR c.cnic_passport LIKE :search OR c.mobile LIKE :search OR c.customer_id_code LIKE :search)";
    $params['search'] = "%{$search}%";
}
if ($statusFilter !== '') {
    $sql .= " AND c.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY c.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customer Registry';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Customer Registry</h4>
        <p class="text-muted small mb-0">Master database of plot buyers, investors and member profiles</p>
    </div>
    <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-hs-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Register New Customer
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, CNIC, mobile or ID..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    <option value="blacklisted" <?= ($statusFilter === 'blacklisted') ? 'selected' : '' ?>>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>CNIC / Passport</th>
                        <th>Mobile</th>
                        <th>City</th>
                        <th>Active Plots</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No customer records found matching the criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-primary border fw-bold"><?= e($c['customer_id_code']) ?></span>
                                </td>
                                <td>
                                    <a href="<?= base_url('modules/customers/view.php?id=' . (int)$c['id']) ?>" class="fw-bold text-decoration-none">
                                        <?= e($c['full_name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.75rem;">S/O <?= e($c['father_husband_name']) ?></div>
                                </td>
                                <td><?= e($c['cnic_passport']) ?></td>
                                <td>
                                    <div><?= e($c['mobile']) ?></div>
                                    <?php if (!empty($c['whatsapp'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['whatsapp']) ?>" target="_blank" class="text-success small text-decoration-none">
                                            <i class="bi bi-whatsapp me-1"></i><?= e($c['whatsapp']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['city']) ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= (int)$c['active_plots_count'] ?></span>
                                </td>
                                <td class="fw-semibold <?= ((float)$c['total_outstanding'] > 0) ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($c['total_outstanding']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= ($c['status'] === 'active') ? 'bg-success' : (($c['status'] === 'blacklisted') ? 'bg-danger' : 'bg-secondary') ?>">
                                        <?= ucfirst(e($c['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/customers/view.php?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Customer 360 View">
                                        <i class="bi bi-person-lines-fill"></i>
                                    </a>
                                    <a href="<?= base_url('modules/customers/edit.php?id=' . (int)$c['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit Customer Profile">
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
