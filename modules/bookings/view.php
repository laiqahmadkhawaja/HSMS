<?php
/**
 * HSMS - View Booking Details & Installment Schedule
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/installment_service.php';
require_permission('bookings_view');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$activeSocId = get_active_society_id();
$society = get_active_society();

$stmt = $db->prepare("
    SELECT bk.*, c.full_name as customer_name, c.customer_id_code, c.cnic_passport, c.mobile, c.whatsapp, c.residential_address, c.city,
           p.plot_number, p.street_road, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name, ps.dimension_details,
           u.name as sales_officer_name, creator.name as created_by_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    LEFT JOIN `users` u ON bk.sales_officer_id = u.id
    LEFT JOIN `users` creator ON bk.created_by = creator.id
    WHERE bk.id = :id AND bk.society_id = :sid
    LIMIT 1
");
$stmt->execute(['id' => $id, 'sid' => $activeSocId]);
$booking = $stmt->fetch();

if (!$booking) {
    redirect_with_message(base_url('modules/bookings/index.php'), 'Booking record not found.', 'danger');
}

// Handle Schedule Revision Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revise_schedule') {
    verify_csrf_or_die();
    require_permission('bookings_create');

    $revReason = trim($_POST['revision_reason'] ?? '');
    $newCount = (int)($_POST['new_installments_count'] ?? 1);
    $newFreq = (string)($_POST['new_frequency'] ?? 'monthly');
    $newStartDate = (string)($_POST['new_start_date'] ?? date('Y-m-d'));
    $newDueDay = (int)($_POST['new_due_day'] ?? 10);

    if (empty($revReason)) {
        redirect_with_message(base_url('modules/bookings/view.php?id=' . $id), 'Revision reason is required.', 'danger');
    }

    try {
        $db->beginTransaction();

        // Calculate outstanding balance to reschedule
        $remainingToSchedule = (float)$booking['remaining_balance'];
        if ($remainingToSchedule <= 0) {
            throw new RuntimeException("This booking has already been fully paid. Cannot revise schedule.");
        }

        // Generate new future installments starting from next installment number
        $stmtMaxNo = $db->prepare("SELECT COALESCE(MAX(installment_no), 0) FROM `installment_schedules` WHERE `booking_id` = :bid AND `amount_paid` > 0");
        $stmtMaxNo->execute(['bid' => $id]);
        $startIno = ((int)$stmtMaxNo->fetchColumn()) + 1;

        $newSchedule = InstallmentService::generateSchedule(
            $remainingToSchedule,
            0.00, // No new down payment
            $newCount,
            $newFreq,
            $newStartDate,
            $newDueDay
        );

        // Adjust installment numbers and titles
        $formattedNew = [];
        foreach ($newSchedule as $item) {
            if ($item['installment_no'] === 0) continue; // Skip zero DP row
            $item['installment_no'] = $startIno++;
            $item['installment_title'] = "Revised Installment #{$item['installment_no']}";
            $formattedNew[] = $item;
        }

        $user = current_user();
        InstallmentService::reviseSchedule($db, $id, $formattedNew, $revReason, $user['id'] ?? 1);

        $db->commit();
        log_audit('SCHEDULE_REVISED', 'bookings', $id, null, ['reason' => $revReason, 'new_count' => $newCount], $activeSocId);
        redirect_with_message(base_url('modules/bookings/view.php?id=' . $id), 'Installment schedule revised successfully!', 'success');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        redirect_with_message(base_url('modules/bookings/view.php?id=' . $id), 'Failed to revise schedule: ' . $e->getMessage(), 'danger');
    }
}

// Fetch Installment Schedule Rows
$stmtSched = $db->prepare("
    SELECT * 
    FROM `installment_schedules` 
    WHERE `booking_id` = :bid 
    ORDER BY `installment_no` ASC
");
$stmtSched->execute(['bid' => $id]);
$schedules = $stmtSched->fetchAll();

// Fetch Schedule Revisions
$stmtRevs = $db->prepare("
    SELECT ir.*, u.name as user_name 
    FROM `installment_revisions` ir
    JOIN `users` u ON ir.approved_by = u.id
    WHERE ir.booking_id = :bid 
    ORDER BY ir.id DESC
");
$stmtRevs->execute(['bid' => $id]);
$revisions = $stmtRevs->fetchAll();

// Fetch Payments / Receipts against this booking
$stmtPay = $db->prepare("
    SELECT pay.*, u.name as collected_by_name
    FROM `payments` pay
    JOIN `users` u ON pay.created_by = u.id
    WHERE pay.booking_id = :bid AND pay.status = 'posted'
    ORDER BY pay.id DESC
");
$stmtPay->execute(['bid' => $id]);
$payments = $stmtPay->fetchAll();

$pageTitle = 'Booking Contract: ' . $booking['booking_number'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">
            Contract: <?= e($booking['booking_number']) ?>
            <span class="status-badge badge-<?= e($booking['status']) ?> ms-2"><?= ucfirst(e($booking['status'])) ?></span>
        </h4>
        <p class="text-muted small mb-0">Booking Date: <strong><?= format_date($booking['booking_date']) ?></strong> | Society: <?= e($society['name'] ?? 'Society') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/bookings/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
        <a href="<?= base_url('modules/bookings/print.php?id=' . $id) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Allotment Order
        </a>
        <?php if ($booking['status'] === 'active'): ?>
            <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . $id) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-cash me-1"></i> Collect Payment
            </a>
            <button class="btn btn-outline-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#reviseModal">
                <i class="bi bi-pencil-square me-1"></i> Revise Schedule
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Contract Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Buyer Info -->
    <div class="col-md-4">
        <div class="card-hs h-100">
            <div class="card-header"><i class="bi bi-person me-1"></i> Buyer Particulars</div>
            <div class="card-body">
                <div class="fw-bold fs-6 mb-1">
                    <a href="<?= base_url('modules/customers/view.php?id=' . (int)$booking['customer_id']) ?>" class="text-decoration-none">
                        <?= e($booking['customer_name']) ?>
                    </a>
                </div>
                <div class="small text-muted mb-2">Member ID: <?= e($booking['customer_id_code']) ?></div>
                <div class="small mb-1"><strong>CNIC:</strong> <?= e($booking['cnic_passport']) ?></div>
                <div class="small mb-1"><strong>Mobile:</strong> <?= e($booking['mobile']) ?></div>
                <div class="small mb-1"><strong>Address:</strong> <?= e($booking['residential_address']) ?>, <?= e($booking['city']) ?></div>
            </div>
        </div>
    </div>

    <!-- Plot Info -->
    <div class="col-md-4">
        <div class="card-hs h-100">
            <div class="card-header"><i class="bi bi-geo-alt me-1"></i> Plot Specifications</div>
            <div class="card-body">
                <div class="fw-bold fs-6 text-primary mb-1">
                    Plot #<?= e($booking['plot_number']) ?> — <?= e($booking['block_name']) ?>
                </div>
                <div class="small text-muted mb-2"><?= e($booking['phase_name']) ?></div>
                <div class="small mb-1"><strong>Size:</strong> <?= e($booking['size_name']) ?> (<?= e($booking['dimension_details'] ?? 'Standard') ?>)</div>
                <div class="small mb-1"><strong>Category:</strong> <?= e($booking['type_name']) ?></div>
                <div class="small mb-1"><strong>Location:</strong> <?= e($booking['street_road'] ?: 'Standard Road') ?></div>
            </div>
        </div>
    </div>

    <!-- Financial Breakdown -->
    <div class="col-md-4">
        <div class="card-hs h-100">
            <div class="card-header"><i class="bi bi-cash-stack me-1"></i> Financial Position</div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Net Sale Price:</span>
                    <span class="fw-bold"><?= format_currency($booking['net_sale_price']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Down Payment Due:</span>
                    <span><?= format_currency($booking['down_payment_amount']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Total Amount Paid:</span>
                    <span class="text-success fw-bold"><?= format_currency($booking['total_paid']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Remaining Balance:</span>
                    <span class="text-danger fw-bold"><?= format_currency($booking['remaining_balance']) ?></span>
                </div>
                <?php if ((float)$booking['advance_balance'] > 0): ?>
                    <div class="d-flex justify-content-between small pt-1 border-top">
                        <span class="text-muted">Customer Advance:</span>
                        <span class="text-primary fw-bold"><?= format_currency($booking['advance_balance']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Complete Installment Schedule Table -->
<div class="card-hs mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar3 me-1"></i> Official Installment Payment Schedule</span>
        <span class="badge bg-light text-dark border"><?= count($schedules) ?> Installments Total</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hs">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Installment Title</th>
                    <th>Due Date</th>
                    <th class="text-end">Amount Due</th>
                    <th class="text-end">Amount Paid</th>
                    <th class="text-end">Balance Due</th>
                    <th>Status</th>
                    <th>Cleared Date</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                    <?php 
                        $isOverdue = ($s['status'] !== 'paid' && strtotime($s['due_date']) < strtotime(date('Y-m-d')));
                        $statusBadgeClass = match($s['status']) {
                            'paid'           => 'bg-success',
                            'partially_paid' => 'bg-warning text-dark',
                            default          => ($isOverdue ? 'bg-danger' : 'bg-secondary')
                        };
                        $displayStatus = $isOverdue ? 'Overdue' : ucfirst(str_replace('_', ' ', $s['status']));
                    ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?= (int)$s['installment_no'] ?></span></td>
                        <td class="fw-semibold">
                            <?= e($s['installment_title']) ?>
                            <?php if ($s['is_custom']): ?><span class="badge bg-info text-dark" style="font-size:0.65rem;">Special</span><?php endif; ?>
                        </td>
                        <td class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>"><?= format_date($s['due_date']) ?></td>
                        <td class="text-end fw-semibold"><?= format_currency($s['amount_due']) ?></td>
                        <td class="text-end text-success fw-semibold"><?= format_currency($s['amount_paid']) ?></td>
                        <td class="text-end fw-bold <?= ((float)$s['balance_due'] > 0) ? 'text-danger' : 'text-success' ?>">
                            <?= format_currency($s['balance_due']) ?>
                        </td>
                        <td><span class="badge <?= $statusBadgeClass ?>"><?= $displayStatus ?></span></td>
                        <td class="small text-muted"><?= format_date($s['paid_date']) ?></td>
                        <td class="text-end">
                            <?php if ($s['status'] !== 'paid' && $booking['status'] === 'active'): ?>
                                <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . $id . '&schedule_id=' . (int)$s['id']) ?>" class="btn btn-sm btn-outline-success py-0 px-2">
                                    Pay
                                </a>
                            <?php else: ?>
                                <i class="bi bi-check2-all text-success"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipts History Table -->
<div class="card-hs mb-4">
    <div class="card-header"><i class="bi bi-receipt me-1"></i> Payment Receipts Posted on this Contract</div>
    <div class="table-responsive">
        <table class="table table-hs">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Payment Date</th>
                    <th>Amount Collected</th>
                    <th>Allocated Amount</th>
                    <th>Advance Amount</th>
                    <th>Payment Method</th>
                    <th>Collected By</th>
                    <th class="text-end">Print</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No payments recorded against this booking yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td>
                                <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$pay['id']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                    <?= e($pay['receipt_number']) ?>
                                </a>
                            </td>
                            <td><?= format_date($pay['payment_date']) ?></td>
                            <td class="fw-bold text-success"><?= format_currency($pay['payment_amount']) ?></td>
                            <td><?= format_currency($pay['allocated_amount']) ?></td>
                            <td class="text-primary"><?= format_currency($pay['advance_amount']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= ucfirst(e($pay['payment_method'])) ?></span></td>
                            <td><?= e($pay['collected_by_name']) ?></td>
                            <td class="text-end">
                                <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$pay['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Schedule Revisions Audit History -->
<?php if (!empty($revisions)): ?>
    <div class="card-hs mb-4">
        <div class="card-header"><i class="bi bi-journal-check me-1"></i> Past Schedule Revisions & Audit History</div>
        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Rev #</th>
                        <th>Date & Time</th>
                        <th>Approved By</th>
                        <th>Justification / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revisions as $rev): ?>
                        <tr>
                            <td><span class="badge bg-secondary">Rev-<?= (int)$rev['revision_number'] ?></span></td>
                            <td><?= format_date($rev['created_at'], 'd M Y, h:i A') ?></td>
                            <td class="fw-semibold"><?= e($rev['user_name']) ?></td>
                            <td><?= e($rev['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Revise Schedule Modal -->
<div class="modal fade" id="reviseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revise_schedule">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Revise Future Installment Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Remaining uncollected balance: <strong><?= format_currency($booking['remaining_balance']) ?></strong>.
                        Past cleared installments will remain intact. Future unpaid installments will be safely recalculated.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Number of Future Installments <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="120" name="new_installments_count" class="form-control form-control-sm" required value="24">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Installment Frequency <span class="text-danger">*</span></label>
                        <select name="new_frequency" class="form-select form-select-sm">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="semi_annual">Semi-Annual</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">First New Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="new_start_date" class="form-control form-control-sm" required value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Revision Reason / Authority Approval <span class="text-danger">*</span></label>
                        <textarea name="revision_reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Customer requested extension approved by Director Sales"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">Execute Schedule Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
