<?php
/**
 * HSMS - Process Plot Booking Cancellation & Inventory Release
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/cancellation_service.php';
require_permission('bookings_cancel');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$preBookingId = (int)($_GET['booking_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $deductionAmount = (float)($_POST['deduction_amount'] ?? 0);
    $cancellationDate = trim($_POST['cancellation_date'] ?? date('Y-m-d'));
    $reason = trim($_POST['reason'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('booking_id', 'Booking Contract')
              ->minNumber('deduction_amount', 0.0, 'Deduction Amount')
              ->required('cancellation_date', 'Cancellation Date')
              ->date('cancellation_date', 'Cancellation Date')
              ->required('reason', 'Reason for Cancellation');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        try {
            $user = current_user();
            $cancellationId = CancellationService::processCancellation(
                $activeSocId,
                $bookingId,
                $deductionAmount,
                $cancellationDate,
                $reason,
                $user['id'] ?? 1
            );

            redirect_with_message(base_url('modules/cancellations/print.php?id=' . $cancellationId), 'Plot booking cancelled successfully and inventory released back to Available!', 'success');
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

$pageTitle = 'Cancel Plot Booking';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
                <span class="fw-bold"><i class="bi bi-x-circle me-2"></i> Terminate & Cancel Plot Booking</span>
                <a href="<?= base_url('modules/cancellations/index.php') ?>" class="btn btn-sm btn-light">Back to Register</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="cancelForm">
                    <?= csrf_field() ?>

                    <!-- Booking Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Active Booking Contract to Cancel <span class="text-danger">*</span></label>
                        <select name="booking_id" id="booking_id" class="form-select form-select-sm <?= isset($errors['booking_id']) ? 'is-invalid' : '' ?>" required onchange="handleBookingChange(this)">
                            <option value="">Choose Booking Contract</option>
                            <?php foreach ($activeBookings as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"
                                    data-customer-name="<?= e($b['customer_name']) ?>"
                                    data-cnic="<?= e($b['cnic_passport']) ?>"
                                    data-mobile="<?= e($b['mobile']) ?>"
                                    data-plot="<?= e($b['plot_number']) ?>"
                                    data-block="<?= e($b['block_name']) ?>"
                                    data-price="<?= (float)$b['net_sale_price'] ?>"
                                    data-paid="<?= (float)$b['total_paid'] ?>"
                                    data-balance="<?= (float)$b['remaining_balance'] ?>"
                                    <?= ($preBookingId === (int)$b['id'] || (int)($_POST['booking_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['booking_number']) ?> — <?= e($b['customer_name']) ?> (Plot #<?= e($b['plot_number']) ?>, <?= e($b['block_name']) ?>) - Total Paid: Rs. <?= number_format((float)$b['total_paid'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['booking_id'])): ?><div class="invalid-feedback"><?= e($errors['booking_id']) ?></div><?php endif; ?>
                    </div>

                    <!-- Contract Details Card -->
                    <div id="bookingDetailsCard" class="card mb-4 bg-light border" style="display: none;">
                        <div class="card-body py-2 px-3">
                            <div class="row g-2 small">
                                <div class="col-md-4">
                                    <span class="text-muted">Buyer Allottee:</span>
                                    <div class="fw-bold" id="cardBuyerName">-</div>
                                    <div class="text-muted" id="cardBuyerCnic">-</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Plot & Sector:</span>
                                    <div class="fw-bold" id="cardPlotBlock">-</div>
                                    <div class="text-muted" id="cardPriceBal">-</div>
                                </div>
                                <div class="col-md-4">
                                    <span class="text-muted">Total Paid by Buyer:</span>
                                    <div class="fw-bold text-success fs-6" id="cardTotalPaid">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Deduction & Refund Calculation -->
                    <div class="p-3 mb-4 rounded bg-light border">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calculator me-1"></i> Policy Deduction & Refund Calculation</h6>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Deduction Policy</label>
                                <div class="btn-group w-100 btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="policy_mode" id="policy_10pct" value="10" checked onchange="calculateDeduction()">
                                    <label class="btn btn-outline-secondary" for="policy_10pct">10% Standard</label>

                                    <input type="radio" class="btn-check" name="policy_mode" id="policy_20pct" value="20" onchange="calculateDeduction()">
                                    <label class="btn btn-outline-secondary" for="policy_20pct">20% Default</label>

                                    <input type="radio" class="btn-check" name="policy_mode" id="policy_custom" value="custom" onchange="calculateDeduction()">
                                    <label class="btn btn-outline-secondary" for="policy_custom">Custom</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Deduction Amount (Rs.) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="deduction_amount" id="deduction_amount" class="form-control form-control-sm <?= isset($errors['deduction_amount']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['deduction_amount'] ?? '0.00') ?>" required oninput="calculateRefund()">
                                <?php if (isset($errors['deduction_amount'])): ?><div class="invalid-feedback"><?= e($errors['deduction_amount']) ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Live Breakdown -->
                        <div class="row g-2 pt-2 border-top">
                            <div class="col-md-4">
                                <span class="text-muted small">Total Paid:</span>
                                <div class="fw-bold text-dark" id="calcPaid">Rs. 0.00</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Society Retention:</span>
                                <div class="fw-bold text-danger" id="calcDeduct">- Rs. 0.00</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Net Refund Liability:</span>
                                <div class="fw-bold text-primary fs-6" id="calcRefund">Rs. 0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Effective Cancellation Date <span class="text-danger">*</span></label>
                            <input type="date" name="cancellation_date" class="form-control form-control-sm <?= isset($errors['cancellation_date']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['cancellation_date'] ?? date('Y-m-d')) ?>" required>
                            <?php if (isset($errors['cancellation_date'])): ?><div class="invalid-feedback"><?= e($errors['cancellation_date']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Approval Authority</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?= e(current_user()['name'] ?? 'Authorized Director') ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Official Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm <?= isset($errors['reason']) ? 'is-invalid' : '' ?>" rows="3" required placeholder="State exact grounds for cancellation (e.g. Failure to clear overdue installments after 3 final notices, or voluntary surrender application submitted by buyer)."><?= e($_POST['reason'] ?? '') ?></textarea>
                        <?php if (isset($errors['reason'])): ?><div class="invalid-feedback"><?= e($errors['reason']) ?></div><?php endif; ?>
                    </div>

                    <div class="alert alert-danger py-2 small mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>IRREVERSIBLE ACTION WARNING:</strong> Once cancelled, the booking contract will be permanently closed, the customer ledger will be reconciled, and the plot will be instantly returned to active inventory available for resale.
                    </div>

                    <div class="text-end">
                        <a href="<?= base_url('modules/cancellations/index.php') ?>" class="btn btn-sm btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger btn-sm px-4 py-2 fw-semibold" onclick="return confirm('CRITICAL WARNING: Are you sure you want to permanently cancel this plot booking and return the plot to available stock?');">
                            <i class="bi bi-x-octagon me-1"></i> Confirm Cancellation & Release Plot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentPaid = 0.0;

function handleBookingChange(select) {
    const opt = select.options[select.selectedIndex];
    const card = document.getElementById('bookingDetailsCard');
    if (!opt || !opt.value) {
        card.style.display = 'none';
        currentPaid = 0.0;
        calculateDeduction();
        return;
    }

    currentPaid = parseFloat(opt.dataset.paid) || 0.0;

    document.getElementById('cardBuyerName').innerText = opt.dataset.customerName;
    document.getElementById('cardBuyerCnic').innerText = 'CNIC: ' + opt.dataset.cnic;
    document.getElementById('cardPlotBlock').innerText = 'Plot #' + opt.dataset.plot + ' (' + opt.dataset.block + ')';
    document.getElementById('cardPriceBal').innerText = 'Net Sale: Rs. ' + Number(opt.dataset.price).toLocaleString() + ' | Rem Bal: Rs. ' + Number(opt.dataset.balance).toLocaleString();
    document.getElementById('cardTotalPaid').innerText = 'Rs. ' + currentPaid.toLocaleString(undefined, {minimumFractionDigits: 2});

    card.style.display = 'block';
    calculateDeduction();
}

function calculateDeduction() {
    const mode = document.querySelector('input[name="policy_mode"]:checked')?.value || '10';
    const deductInput = document.getElementById('deduction_amount');

    if (mode === '10') {
        deductInput.value = (currentPaid * 0.10).toFixed(2);
        deductInput.readOnly = true;
    } else if (mode === '20') {
        deductInput.value = (currentPaid * 0.20).toFixed(2);
        deductInput.readOnly = true;
    } else {
        deductInput.readOnly = false;
    }
    calculateRefund();
}

function calculateRefund() {
    const deduct = parseFloat(document.getElementById('deduction_amount').value) || 0.0;
    const refund = Math.max(0.0, currentPaid - deduct);

    document.getElementById('calcPaid').innerText = 'Rs. ' + currentPaid.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('calcDeduct').innerText = '- Rs. ' + deduct.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('calcRefund').innerText = 'Rs. ' + refund.toLocaleString(undefined, {minimumFractionDigits: 2});
}

document.addEventListener('DOMContentLoaded', function() {
    const bkSelect = document.getElementById('booking_id');
    if (bkSelect && bkSelect.value) {
        handleBookingChange(bkSelect);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
