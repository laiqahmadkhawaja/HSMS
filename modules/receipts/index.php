<?php
/**
 * HSMS - Receipts Master Register & Reversal Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/payment_service.php';
require_permission('payments_collect');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

// Handle Payment Reversal Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reverse_payment') {
    verify_csrf_or_die();
    require_permission('payments_reverse');

    $payId = (int)($_POST['payment_id'] ?? 0);
    $revReason = trim($_POST['reversal_reason'] ?? '');

    if ($payId <= 0 || empty($revReason)) {
        redirect_with_message(base_url('modules/receipts/index.php'), 'Valid Payment ID and Reversal Reason are required.', 'danger');
    }

    try {
        $user = current_user();
        PaymentService::reversePayment($payId, $revReason, $user['id'] ?? 1);
        redirect_with_message(base_url('modules/receipts/index.php'), 'Payment receipt reversed successfully with audit trail!', 'success');
    } catch (Throwable $e) {
        redirect_with_message(base_url('modules/receipts/index.php'), 'Failed to reverse payment: ' . $e->getMessage(), 'danger');
    }
}

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT pay.*, c.full_name as customer_name, c.cnic_passport, bk.booking_number, p.plot_number, b.block_name,
           acc.account_name, u.name as collected_by_name
    FROM `payments` pay
    JOIN `customers` c ON pay.customer_id = c.id
    JOIN `bookings` bk ON pay.booking_id = bk.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `accounts` acc ON pay.cash_bank_account_id = acc.id
    JOIN `users` u ON pay.created_by = u.id
    WHERE pay.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (pay.receipt_number LIKE :s1 OR c.full_name LIKE :s2 OR bk.booking_number LIKE :s3 OR p.plot_number LIKE :s4)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
}
if ($methodFilter !== '') {
    $sql .= " AND pay.payment_method = :method";
    $params['method'] = $methodFilter;
}
if ($statusFilter !== '') {
    $sql .= " AND pay.status = :status";
    $params['status'] = $statusFilter;
}
if ($startDate !== '') {
    $sql .= " AND pay.payment_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND pay.payment_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY pay.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

$pageTitle = 'Receipts Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Receipts Register</h4>
        <p class="text-muted small mb-0">Record of all recovery payments collected for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('modules/recoveries/create.php') ?>" class="btn btn-success btn-sm">
        <i class="bi bi-cash-stack me-1"></i> Post New Payment
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Receipt #, Customer, Plot #..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="method" class="form-select form-select-sm">
                    <option value="">All Payment Methods</option>
                    <option value="cash" <?= ($methodFilter === 'cash') ? 'selected' : '' ?>>Cash</option>
                    <option value="bank_transfer" <?= ($methodFilter === 'bank_transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="cheque" <?= ($methodFilter === 'cheque') ? 'selected' : '' ?>>Cheque</option>
                    <option value="pay_order" <?= ($methodFilter === 'pay_order') ? 'selected' : '' ?>>Pay Order</option>
                    <option value="jazzcash" <?= ($methodFilter === 'jazzcash') ? 'selected' : '' ?>>JazzCash</option>
                    <option value="easypaisa" <?= ($methodFilter === 'easypaisa') ? 'selected' : '' ?>>Easypaisa</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="posted" <?= ($statusFilter === 'posted') ? 'selected' : '' ?>>Posted</option>
                    <option value="reversed" <?= ($statusFilter === 'reversed') ? 'selected' : '' ?>>Reversed</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="From Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" placeholder="To Date">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel"></i></button>
                <a href="<?= base_url('modules/receipts/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Buyer Name</th>
                        <th>Booking / Plot</th>
                        <th class="text-end">Amount Paid</th>
                        <th class="text-end">Allocated</th>
                        <th class="text-end">Advance</th>
                        <th>Method & Account</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receipts)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No payment receipts found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$r['id']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                        <?= e($r['receipt_number']) ?>
                                    </a>
                                </td>
                                <td><?= format_date($r['payment_date']) ?></td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$r['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($r['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;"><?= e($r['cnic_passport']) ?></div>
                                </td>
                                <td>
                                    <div><a href="<?= base_url('modules/bookings/view.php?id=' . (int)$r['booking_id']) ?>" class="small text-decoration-none"><?= e($r['booking_number']) ?></a></div>
                                    <span class="badge bg-secondary"><?= e($r['plot_number']) ?></span>
                                </td>
                                <td class="text-end fw-bold text-success"><?= format_currency($r['payment_amount']) ?></td>
                                <td class="text-end fw-semibold"><?= format_currency($r['allocated_amount']) ?></td>
                                <td class="text-end text-primary"><?= ((float)$r['advance_amount'] > 0) ? format_currency($r['advance_amount']) : '—' ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= ucfirst(e($r['payment_method'])) ?></span>
                                    <div class="text-muted mt-1" style="font-size:0.72rem;"><?= e($r['account_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= ($r['status'] === 'posted') ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst(e($r['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$r['id']) ?>" target="_blank" class="btn btn-outline-secondary py-0 px-2" title="Print Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($r['status'] === 'posted' && has_permission('payments_reverse')): ?>
                                            <button type="button" class="btn btn-outline-danger py-0 px-2" title="Reverse Receipt" onclick="prepareReversal(<?= (int)$r['id'] ?>, '<?= e($r['receipt_number']) ?>')">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
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

<!-- Reversal Modal -->
<div class="modal fade" id="reversalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reverse_payment">
                <input type="hidden" name="payment_id" id="rev_payment_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Reverse Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Are you sure you want to reverse Receipt <strong id="rev_receipt_num" class="text-dark"></strong>?
                        This will roll back all installment allocations, debit the customer ledger, and reverse the account balances.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Audit Reversal Justification / Reason <span class="text-danger">*</span></label>
                        <textarea name="reversal_reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Cheque returned unpaid / Entry posted to incorrect plot"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">Confirm Reversal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareReversal(payId, rNum) {
    document.getElementById('rev_payment_id').value = payId;
    document.getElementById('rev_receipt_num').textContent = rNum;
    const modal = new bootstrap.Modal(document.getElementById('reversalModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
