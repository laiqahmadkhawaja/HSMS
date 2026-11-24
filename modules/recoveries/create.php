<?php
/**
 * HSMS - Collect Payment & Post Receipt
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/payment_service.php';
require_permission('payments_collect');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$preSelectedBookingId = (int)($_GET['booking_id'] ?? 0);
$preSelectedScheduleId = (int)($_GET['schedule_id'] ?? 0);
$errors = [];

// Fetch Active Bookings in Society for dropdown
$stmtBks = $db->prepare("
    SELECT bk.id, bk.booking_number, bk.customer_id, bk.net_sale_price, bk.total_paid, bk.remaining_balance,
           c.full_name as customer_name, c.cnic_passport, c.mobile,
           p.plot_number, b.block_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    WHERE bk.society_id = :sid AND bk.status = 'active'
    ORDER BY bk.id DESC
");
$stmtBks->execute(['sid' => $activeSocId]);
$activeBookings = $stmtBks->fetchAll();

// Fetch Cash & Bank Receiving Accounts (Assets where sub_type IN ('cash', 'bank'))
$stmtAccounts = $db->query("
    SELECT id, account_code, account_name, sub_type, current_balance 
    FROM `accounts` 
    WHERE `account_type` = 'asset' AND `sub_type` IN ('cash', 'bank') AND `status` = 'active'
    ORDER BY `sub_type` ASC, `account_name` ASC
");
$accounts = $stmtAccounts->fetchAll();

// Fetch Recovery Officers
$stmtOfficers = $db->prepare("
    SELECT ro.id, u.name as officer_name 
    FROM `recovery_officers` ro
    JOIN `users` u ON ro.user_id = u.id
    WHERE ro.society_id = :sid AND ro.status = 'active'
");
$stmtOfficers->execute(['sid' => $activeSocId]);
$recoveryOfficers = $stmtOfficers->fetchAll();

// Pre-load selected booking details & schedules if specified
$selectedBooking = null;
$unpaidSchedules = [];
if ($preSelectedBookingId > 0) {
    foreach ($activeBookings as $b) {
        if ((int)$b['id'] === $preSelectedBookingId) {
            $selectedBooking = $b;
            break;
        }
    }
    if ($selectedBooking) {
        $stmtSched = $db->prepare("
            SELECT id, installment_no, installment_title, due_date, amount_due, amount_paid, balance_due, status
            FROM `installment_schedules`
            WHERE `booking_id` = :bid AND `balance_due` > 0
            ORDER BY `installment_no` ASC
        ");
        $stmtSched->execute(['bid' => $preSelectedBookingId]);
        $unpaidSchedules = $stmtSched->fetchAll();
    }
}

// Generate an Idempotency Key for this form session
$idempotencyKey = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
    $paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $paymentMethod = (string)($_POST['payment_method'] ?? 'cash');
    $cashBankAccountId = (int)($_POST['cash_bank_account_id'] ?? 0);
    $transactionRef = trim($_POST['transaction_ref'] ?? '');
    $allocationMethod = in_array($_POST['allocation_method'] ?? '', ['fifo', 'specific']) ? $_POST['allocation_method'] : 'fifo';
    $specificScheduleId = (int)($_POST['specific_schedule_id'] ?? 0) ?: null;
    $officerId = (int)($_POST['recovery_officer_id'] ?? 0) ?: null;
    $remarks = trim($_POST['remarks'] ?? '');
    $postIkey = trim($_POST['idempotency_key'] ?? '');

    // Get customer ID of this booking
    $stmtBkCust = $db->prepare("SELECT customer_id FROM `bookings` WHERE `id` = :bid LIMIT 1");
    $stmtBkCust->execute(['bid' => $bookingId]);
    $customerId = (int)$stmtBkCust->fetchColumn();

    $validator = new Validator($_POST);
    $validator->required('booking_id', 'Booking Contract')
              ->minNumber('payment_amount', 1.0, 'Payment Amount')
              ->required('payment_date', 'Payment Date')
              ->date('payment_date', 'Payment Date')
              ->required('cash_bank_account_id', 'Deposit Account');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } elseif ($customerId <= 0) {
        $errors['booking_id'] = 'Invalid booking contract selected.';
    } else {
        try {
            $user = current_user();
            $paymentId = PaymentService::postPayment(
                $activeSocId,
                $bookingId,
                $customerId,
                $paymentAmount,
                $paymentDate,
                $paymentMethod,
                $cashBankAccountId,
                $transactionRef,
                $allocationMethod,
                $specificScheduleId,
                $officerId,
                $remarks,
                $postIkey,
                $user['id'] ?? 1
            );

            redirect_with_message(base_url('modules/receipts/print.php?id=' . $paymentId), 'Payment posted successfully!', 'success');
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

$pageTitle = 'Collect Payment & Post Receipt';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-cash-stack me-2"></i> Post Customer Payment Receipt</span>
                <a href="<?= base_url('modules/recoveries/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Recovery Desk</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="paymentForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="idempotency_key" value="<?= e($idempotencyKey) ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Active Booking Contract / Plot <span class="text-danger">*</span></label>
                        <select name="booking_id" id="booking_id" class="form-select form-select-sm <?= isset($errors['booking_id']) ? 'is-invalid' : '' ?>" required onchange="handleBookingSelection(this.value)">
                            <option value="">Choose Booking Account</option>
                            <?php foreach ($activeBookings as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (($preSelectedBookingId === (int)$b['id']) || ((int)($_POST['booking_id'] ?? 0) === (int)$b['id'])) ? 'selected' : '' ?>>
                                    <?= e($b['booking_number']) ?> — <?= e($b['customer_name']) ?> (Plot #<?= e($b['plot_number']) ?>, <?= e($b['block_name']) ?>) - Balance: Rs. <?= number_format((float)$b['remaining_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['booking_id'])): ?><div class="invalid-feedback"><?= e($errors['booking_id']) ?></div><?php endif; ?>
                    </div>

                    <?php if ($selectedBooking): ?>
                        <div class="p-3 mb-4 rounded bg-light border" id="accountSummaryBox">
                            <div class="row g-2 small">
                                <div class="col-sm-3">
                                    <div class="text-muted">Buyer Name:</div>
                                    <div class="fw-bold"><?= e($selectedBooking['customer_name']) ?></div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="text-muted">Plot & Block:</div>
                                    <div class="fw-bold">Plot #<?= e($selectedBooking['plot_number']) ?> (<?= e($selectedBooking['block_name']) ?>)</div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="text-muted">Total Paid:</div>
                                    <div class="text-success fw-bold"><?= format_currency($selectedBooking['total_paid']) ?></div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="text-muted">Remaining Balance:</div>
                                    <div class="text-danger fw-bold"><?= format_currency($selectedBooking['remaining_balance']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Amount (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="payment_amount" id="payment_amount" 
                                   class="form-control form-control-sm <?= isset($errors['payment_amount']) ? 'is-invalid' : '' ?>" 
                                   required placeholder="e.g. 50000" value="<?= e($_POST['payment_amount'] ?? '') ?>">
                            <?php if (isset($errors['payment_amount'])): ?><div class="invalid-feedback"><?= e($errors['payment_amount']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Collection Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control form-control-sm" required value="<?= e($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select form-select-sm" required>
                                <option value="cash">Cash on Hand</option>
                                <option value="bank_transfer">Bank Transfer / Online</option>
                                <option value="cheque">Cheque</option>
                                <option value="pay_order">Pay Order</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Receiving Account (Cash / Bank) <span class="text-danger">*</span></label>
                            <select name="cash_bank_account_id" class="form-select form-select-sm <?= isset($errors['cash_bank_account_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Receiving Account</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((int)($_POST['cash_bank_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>>
                                        <?= e($acc['account_name']) ?> (<?= strtoupper(e($acc['sub_type'])) ?> — Code: <?= e($acc['account_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['cash_bank_account_id'])): ?><div class="invalid-feedback"><?= e($errors['cash_bank_account_id']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cheque No / Transfer Reference</label>
                            <input type="text" name="transaction_ref" class="form-control form-control-sm" placeholder="e.g. HBL-CHQ-981244" value="<?= e($_POST['transaction_ref'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recovery Field Officer (If Applicable)</label>
                            <select name="recovery_officer_id" class="form-select form-select-sm">
                                <option value="">Direct Office Collection</option>
                                <?php foreach ($recoveryOfficers as $ro): ?>
                                    <option value="<?= (int)$ro['id'] ?>"><?= e($ro['officer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 mb-4 rounded bg-light border">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-distribute-vertical me-1"></i> Allocation Engine Policy</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="allocation_method" id="alloc_fifo" value="fifo" checked onchange="toggleSpecificSchedule(false)">
                                    <label class="form-check-label fw-semibold" for="alloc_fifo">
                                        FIFO: Oldest Overdue Installment First (Default Recommended)
                                    </label>
                                    <div class="text-muted" style="font-size: 0.75rem;">Automatically pays down payment and oldest overdue installments. Excess payment is credited as customer advance.</div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="allocation_method" id="alloc_specific" value="specific" onchange="toggleSpecificSchedule(true)">
                                    <label class="form-check-label fw-semibold" for="alloc_specific">
                                        Specific Target Installment
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6" id="specificScheduleBox" style="display: none;">
                                <label class="form-label small fw-bold">Select Specific Installment</label>
                                <select name="specific_schedule_id" id="specific_schedule_id" class="form-select form-select-sm">
                                    <option value="">Choose Installment</option>
                                    <?php foreach ($unpaidSchedules as $us): ?>
                                        <option value="<?= (int)$us['id'] ?>" <?= ($preSelectedScheduleId === (int)$us['id']) ? 'selected' : '' ?>>
                                            #<?= (int)$us['installment_no'] ?>: <?= e($us['installment_title']) ?> (Due: Rs. <?= number_format((float)$us['balance_due'], 2) ?> on <?= format_date($us['due_date']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Payment Remarks</label>
                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Counter cash receipt cleared" value="<?= e($_POST['remarks'] ?? '') ?>">
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Post Receipt & Print
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function handleBookingSelection(bkId) {
    if (bkId) {
        window.location.href = '<?= base_url("modules/recoveries/create.php?booking_id=") ?>' + bkId;
    }
}

function toggleSpecificSchedule(show) {
    document.getElementById('specificScheduleBox').style.display = show ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($preSelectedScheduleId > 0): ?>
        document.getElementById('alloc_specific').checked = true;
        toggleSpecificSchedule(true);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
