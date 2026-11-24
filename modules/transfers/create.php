<?php
/**
 * HSMS - Execute Plot Ownership Transfer
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/transfer_service.php';
require_permission('transfers_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$preBookingId = (int)($_GET['booking_id'] ?? 0);
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $toCustomerId = (int)($_POST['to_customer_id'] ?? 0);
    $transferFee = (float)($_POST['transfer_fee'] ?? 0);
    $transferFeeStatus = in_array($_POST['transfer_fee_status'] ?? '', ['paid', 'waived', 'pending'], true) ? $_POST['transfer_fee_status'] : 'paid';
    $feeAccountId = (int)($_POST['cash_bank_account_id'] ?? 0) ?: null;
    $transferDate = trim($_POST['transfer_date'] ?? date('Y-m-d'));
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('booking_id', 'Booking Contract')
              ->required('to_customer_id', 'Target Buyer')
              ->minNumber('transfer_fee', 0.0, 'Transfer Fee')
              ->required('transfer_date', 'Transfer Date')
              ->date('transfer_date', 'Transfer Date');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        try {
            $user = current_user();
            $transferId = TransferService::processTransfer(
                $activeSocId,
                $bookingId,
                $toCustomerId,
                $transferFee,
                $transferFeeStatus,
                $feeAccountId,
                $transferDate,
                $remarks,
                $user['id'] ?? 1
            );

            redirect_with_message(base_url('modules/transfers/print.php?id=' . $transferId), 'Plot ownership transfer executed successfully!', 'success');
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

// Fetch active bookings in society
$stmtBks = $db->prepare("
    SELECT bk.id, bk.booking_number, bk.customer_id, bk.net_sale_price, bk.total_paid, bk.remaining_balance,
           c.full_name as customer_name, c.cnic_passport, c.mobile,
           p.plot_number, b.block_name, ph.phase_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    WHERE bk.society_id = :sid AND bk.status = 'active'
    ORDER BY bk.id DESC
");
$stmtBks->execute(['sid' => $activeSocId]);
$activeBookings = $stmtBks->fetchAll();

// Fetch active customers for buyer selector
$stmtCust = $db->query("
    SELECT id, full_name, cnic_passport, mobile, email, city 
    FROM `customers` 
    WHERE `status` = 'active' 
    ORDER BY `full_name` ASC
");
$customers = $stmtCust->fetchAll();

// Fetch Receiving Accounts (Cash / Bank)
$stmtAccounts = $db->query("
    SELECT id, account_code, account_name, sub_type, current_balance 
    FROM `accounts` 
    WHERE `account_type` = 'asset' AND `sub_type` IN ('cash', 'bank') AND `status` = 'active'
    ORDER BY `sub_type` ASC, `account_name` ASC
");
$accounts = $stmtAccounts->fetchAll();

$pageTitle = 'Execute Plot Transfer';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i> Process Plot Ownership Transfer</span>
                <a href="<?= base_url('modules/transfers/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Register</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>

                    <!-- 1. Booking Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Active Booking Contract to Transfer <span class="text-danger">*</span></label>
                        <select name="booking_id" id="booking_id" class="form-select form-select-sm <?= isset($errors['booking_id']) ? 'is-invalid' : '' ?>" required onchange="handleBookingChange(this)">
                            <option value="">Choose Booking Account</option>
                            <?php foreach ($activeBookings as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" 
                                    data-customer-id="<?= (int)$b['customer_id'] ?>"
                                    data-customer-name="<?= e($b['customer_name']) ?>"
                                    data-cnic="<?= e($b['cnic_passport']) ?>"
                                    data-mobile="<?= e($b['mobile']) ?>"
                                    data-plot="<?= e($b['plot_number']) ?>"
                                    data-block="<?= e($b['block_name']) ?>"
                                    data-price="<?= (float)$b['net_sale_price'] ?>"
                                    data-paid="<?= (float)$b['total_paid'] ?>"
                                    data-balance="<?= (float)$b['remaining_balance'] ?>"
                                    <?= ($preBookingId === (int)$b['id'] || (int)($_POST['booking_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['booking_number']) ?> — <?= e($b['customer_name']) ?> (Plot #<?= e($b['plot_number']) ?>, <?= e($b['block_name']) ?>) - Balance: Rs. <?= number_format((float)$b['remaining_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['booking_id'])): ?><div class="invalid-feedback"><?= e($errors['booking_id']) ?></div><?php endif; ?>
                    </div>

                    <!-- Booking & Seller Info Card -->
                    <div id="bookingDetailsCard" class="card mb-4 bg-light border" style="display: none;">
                        <div class="card-body py-2 px-3">
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <span class="text-muted">Current Owner (Seller):</span>
                                    <div class="fw-bold" id="cardSellerName">-</div>
                                    <div class="text-muted" id="cardSellerCnic">-</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Plot & Sector:</span>
                                    <div class="fw-bold" id="cardPlotBlock">-</div>
                                    <div class="text-muted" id="cardPricePaid">-</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Balance to be Taken Over:</span>
                                    <div class="fw-bold text-danger fs-6" id="cardRemainingBal">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Target Buyer Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select New Allottee / Buyer (Customer) <span class="text-danger">*</span></label>
                        <select name="to_customer_id" id="to_customer_id" class="form-select form-select-sm <?= isset($errors['to_customer_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">Choose Registered Customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)($_POST['to_customer_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= e($c['full_name']) ?> — CNIC: <?= e($c['cnic_passport']) ?> (<?= e($c['mobile']) ?>) - <?= e($c['city']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['to_customer_id'])): ?><div class="invalid-feedback"><?= e($errors['to_customer_id']) ?></div><?php endif; ?>
                        <div class="form-text small">Buyer must be registered in the Customer Registry with verified CNIC/KYC.</div>
                    </div>

                    <!-- 3. Transfer Fee & Account -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Transfer Fee (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="transfer_fee" class="form-control form-control-sm <?= isset($errors['transfer_fee']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['transfer_fee'] ?? '50000.00') ?>" required>
                            <?php if (isset($errors['transfer_fee'])): ?><div class="invalid-feedback"><?= e($errors['transfer_fee']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Transfer Fee Status</label>
                            <select name="transfer_fee_status" id="transfer_fee_status" class="form-select form-select-sm" onchange="toggleFeeAccount(this.value)">
                                <option value="paid" <?= (($_POST['transfer_fee_status'] ?? 'paid') === 'paid') ? 'selected' : '' ?>>Paid (Cash / Bank)</option>
                                <option value="waived" <?= (($_POST['transfer_fee_status'] ?? '') === 'waived') ? 'selected' : '' ?>>Waived (Management Approval)</option>
                                <option value="pending" <?= (($_POST['transfer_fee_status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending Collection</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="feeAccountBox">
                            <label class="form-label fw-bold">Receiving Account</label>
                            <select name="cash_bank_account_id" class="form-select form-select-sm">
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((int)($_POST['cash_bank_account_id'] ?? 1) === (int)$acc['id']) ? 'selected' : '' ?>>
                                        <?= e($acc['account_name']) ?> (<?= ucfirst($acc['sub_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Effective Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" class="form-control form-control-sm <?= isset($errors['transfer_date']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['transfer_date'] ?? date('Y-m-d')) ?>" required>
                            <?php if (isset($errors['transfer_date'])): ?><div class="invalid-feedback"><?= e($errors['transfer_date']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Transfer Remarks / Reference</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Mutual consent affidavit deed verified" value="<?= e($_POST['remarks'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-4">
                        <i class="bi bi-info-circle-fill me-1"></i>
                        <strong>Legal & Ledger Notice:</strong> Upon execution, legal plot ownership and all future installment obligations will transfer to the new allottee. A balanced double-entry journal entry will be posted for any collected transfer fee.
                    </div>

                    <div class="text-end">
                        <a href="<?= base_url('modules/transfers/index.php') ?>" class="btn btn-sm btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm px-4 py-2 fw-semibold" onclick="return confirm('Are you sure you want to execute this plot transfer? This action is legally binding and will reassign all customer balances.');">
                            <i class="bi bi-check-circle me-1"></i> Execute Transfer & Print Deed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function handleBookingChange(select) {
    const opt = select.options[select.selectedIndex];
    const card = document.getElementById('bookingDetailsCard');
    if (!opt || !opt.value) {
        card.style.display = 'none';
        return;
    }

    document.getElementById('cardSellerName').innerText = opt.dataset.customerName;
    document.getElementById('cardSellerCnic').innerText = 'CNIC: ' + opt.dataset.cnic + ' | ' + opt.dataset.mobile;
    document.getElementById('cardPlotBlock').innerText = 'Plot #' + opt.dataset.plot + ' (' + opt.dataset.block + ')';
    document.getElementById('cardPricePaid').innerText = 'Net Sale: Rs. ' + Number(opt.dataset.price).toLocaleString() + ' | Paid: Rs. ' + Number(opt.dataset.paid).toLocaleString();
    document.getElementById('cardRemainingBal').innerText = 'Rs. ' + Number(opt.dataset.balance).toLocaleString();

    card.style.display = 'block';

    // Prevent selecting same customer as buyer
    const buyerSelect = document.getElementById('to_customer_id');
    const sellerCid = opt.dataset.customerId;
    for (let i = 0; i < buyerSelect.options.length; i++) {
        if (buyerSelect.options[i].value === sellerCid) {
            buyerSelect.options[i].disabled = true;
        } else {
            buyerSelect.options[i].disabled = false;
        }
    }
}

function toggleFeeAccount(status) {
    document.getElementById('feeAccountBox').style.display = (status === 'paid') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const bkSelect = document.getElementById('booking_id');
    if (bkSelect && bkSelect.value) {
        handleBookingChange(bkSelect);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
