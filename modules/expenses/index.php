<?php
/**
 * HSMS - Expense Voucher Register
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$search = trim($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT exp.*, ec.name as category_name, acc.account_name as payment_account_name, acc.sub_type as account_sub_type,
           u.name as created_by_name
    FROM `expenses` exp
    JOIN `expense_categories` ec ON exp.expense_category_id = ec.id
    JOIN `accounts` acc ON exp.payment_account_id = acc.id
    JOIN `users` u ON exp.created_by = u.id
    WHERE exp.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (exp.voucher_number LIKE :s1 OR exp.paid_to LIKE :s2 OR exp.reference_no LIKE :s3 OR exp.description LIKE :s4)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
}
if ($categoryFilter > 0) {
    $sql .= " AND exp.expense_category_id = :cid";
    $params['cid'] = $categoryFilter;
}
if ($startDate !== '') {
    $sql .= " AND exp.expense_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND exp.expense_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY exp.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Fetch categories for filter dropdown
$stmtCats = $db->query("SELECT id, name FROM `expense_categories` WHERE `status` = 'active' ORDER BY `name` ASC");
$categories = $stmtCats->fetchAll();

// Calculate total expenses matching filter
$totalExpenseSum = 0.0;
foreach ($expenses as $eRow) {
    $totalExpenseSum += (float)$eRow['amount'];
}

$pageTitle = 'Expense Vouchers';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Expense Vouchers & Disbursements</h4>
        <p class="text-muted small mb-0">Operational and site development expense register for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/accounts/cash_bank.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Cash & Bank Book
        </a>
        <a href="<?= base_url('modules/expenses/create.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Record Expense
        </a>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Voucher #, Payee, Ref or Notes..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Expense Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= ($categoryFilter === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="From Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" placeholder="To Date">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-secondary w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Voucher # / Date</th>
                        <th>Category</th>
                        <th>Paid To (Payee)</th>
                        <th>Payment Account</th>
                        <th>Description / Ref</th>
                        <th class="text-end">Amount (Rs.)</th>
                        <th>Receipt</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No expense vouchers recorded matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold font-monospace text-danger"><?= e($exp['voucher_number']) ?></div>
                                    <div class="text-muted small"><?= date('d M Y', strtotime($exp['expense_date'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($exp['category_name']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($exp['paid_to']) ?></div>
                                </td>
                                <td>
                                    <div><?= e($exp['payment_account_name']) ?></div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.65rem;"><?= ucfirst($exp['account_sub_type']) ?></span>
                                </td>
                                <td>
                                    <div><?= e($exp['description'] ?: '-') ?></div>
                                    <?php if ($exp['reference_no']): ?>
                                        <div class="text-muted small">Ref: <?= e($exp['reference_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold fs-6 text-danger">
                                    <?= format_currency($exp['amount']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($exp['attachment_path'])): ?>
                                        <a href="<?= base_url(e($exp['attachment_path'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Receipt">
                                            <i class="bi bi-paperclip"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($exp['created_by_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5" class="text-end text-uppercase">Total Disbursed:</td>
                            <td class="text-end text-danger fs-6"><?= format_currency($totalExpenseSum) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
