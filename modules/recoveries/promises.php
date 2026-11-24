<?php
/**
 * HSMS - Promises to Pay (PTP) Tracking & Field Follow-ups
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('payments_collect');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];
$preBookingId = (int)($_GET['booking_id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_ptp') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $officerId = (int)($_POST['recovery_officer_id'] ?? 0);
        $contactDate = trim($_POST['contact_date'] ?? date('Y-m-d'));
        $promiseDate = trim($_POST['promise_date'] ?? '');
        $promisedAmount = (float)($_POST['promised_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Fetch customer_id from booking
        $stmtBk = $db->prepare("SELECT customer_id, remaining_balance FROM `bookings` WHERE `id` = :bid AND `society_id` = :sid LIMIT 1");
        $stmtBk->execute(['bid' => $bookingId, 'sid' => $activeSocId]);
        $bk = $stmtBk->fetch();

        if (!$bk) {
            $errors['booking_id'] = 'Please select a valid active booking contract.';
        }
        if ($officerId <= 0) {
            $errors['recovery_officer_id'] = 'Please assign a recovery field officer.';
        }
        if ($promisedAmount <= 0) {
            $errors['promised_amount'] = 'Promised amount must be greater than zero.';
        }
        if (empty($promiseDate)) {
            $errors['promise_date'] = 'Promised payment date is required.';
        }

        if (empty($errors)) {
            $stmtIns = $db->prepare("
                INSERT INTO `promises_to_pay` (
                    `booking_id`, `customer_id`, `recovery_officer_id`,
                    `contact_date`, `promise_date`, `promised_amount`,
                    `notes`, `status`
                ) VALUES (
                    :bid, :cid, :roid,
                    :cdate, :pdate, :amt,
                    :notes, 'pending'
                )
            ");
            $stmtIns->execute([
                'bid'   => $bookingId,
                'cid'   => $bk['customer_id'],
                'roid'  => $officerId,
                'cdate' => $contactDate,
                'pdate' => $promiseDate,
                'amt'   => $promisedAmount,
                'notes' => $notes ?: null
            ]);
            $ptpId = (int)$db->lastInsertId();

            log_audit('PTP_CREATED', 'promises_to_pay', $ptpId, null, [
                'booking_id' => $bookingId,
                'amount'     => $promisedAmount,
                'due_date'   => $promiseDate
            ], $activeSocId);

            redirect_with_message(base_url('modules/recoveries/promises.php'), 'Promise to Pay logged successfully!', 'success');
        }
    } elseif ($action === 'update_status') {
        $ptpId = (int)($_POST['ptp_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        if (!in_array($newStatus, ['pending', 'fulfilled', 'broken', 'rescheduled'], true)) {
            redirect_with_message(base_url('modules/recoveries/promises.php'), 'Invalid status specified.', 'danger');
        }

        $stmtCheck = $db->prepare("
            SELECT ptp.* FROM `promises_to_pay` ptp
            JOIN `bookings` bk ON ptp.booking_id = bk.id
            WHERE ptp.id = :id AND bk.society_id = :sid
        ");
        $stmtCheck->execute(['id' => $ptpId, 'sid' => $activeSocId]);
        $ptp = $stmtCheck->fetch();

        if (!$ptp) {
            redirect_with_message(base_url('modules/recoveries/promises.php'), 'Promise record not found.', 'danger');
        }

        $stmtUpd = $db->prepare("UPDATE `promises_to_pay` SET `status` = :stat WHERE `id` = :id");
        $stmtUpd->execute(['stat' => $newStatus, 'id' => $ptpId]);

        log_audit('PTP_STATUS_UPDATED', 'promises_to_pay', $ptpId, null, [
            'previous_status' => $ptp['status'],
            'new_status'      => $newStatus
        ], $activeSocId);

        redirect_with_message(base_url('modules/recoveries/promises.php'), 'Promise to Pay marked as ' . ucfirst($newStatus) . '!', 'success');
    }
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$officerFilter = (int)($_GET['officer_id'] ?? 0);
$dateFilter = trim($_GET['date_type'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

// Base query for PTP list
$sql = "
    SELECT ptp.*, c.full_name as customer_name, c.mobile, c.whatsapp, c.cnic_passport,
           bk.booking_number, bk.remaining_balance, p.plot_number, b.block_name,
           u.name as officer_name
    FROM `promises_to_pay` ptp
    JOIN `customers` c ON ptp.customer_id = c.id
    JOIN `bookings` bk ON ptp.booking_id = bk.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `recovery_officers` ro ON ptp.recovery_officer_id = ro.id
    JOIN `users` u ON ro.user_id = u.id
    WHERE bk.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (c.full_name LIKE :s1 OR c.cnic_passport LIKE :s2 OR c.mobile LIKE :s3 OR bk.booking_number LIKE :s4 OR p.plot_number LIKE :s5)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
    $params['s3'] = "%{$search}%";
    $params['s4'] = "%{$search}%";
    $params['s5'] = "%{$search}%";
}
if ($statusFilter !== '') {
    $sql .= " AND ptp.status = :stat";
    $params['stat'] = $statusFilter;
}
if ($officerFilter > 0) {
    $sql .= " AND ptp.recovery_officer_id = :roid";
    $params['roid'] = $officerFilter;
}
if ($startDate !== '') {
    $sql .= " AND ptp.promise_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND ptp.promise_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY (CASE WHEN ptp.status = 'pending' THEN 0 ELSE 1 END) ASC, ptp.promise_date ASC, ptp.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$promises = $stmt->fetchAll();

// KPI Summary Counters
$stmtKpi = $db->prepare("
    SELECT
        COUNT(CASE WHEN ptp.status = 'pending' THEN 1 END) as pending_count,
        COALESCE(SUM(CASE WHEN ptp.status = 'pending' THEN ptp.promised_amount ELSE 0 END), 0) as pending_amount,
        COUNT(CASE WHEN ptp.status = 'pending' AND ptp.promise_date < CURDATE() THEN 1 END) as overdue_ptp_count,
        COUNT(CASE WHEN ptp.status = 'fulfilled' THEN 1 END) as fulfilled_count,
        COALESCE(SUM(CASE WHEN ptp.status = 'fulfilled' THEN ptp.promised_amount ELSE 0 END), 0) as fulfilled_amount,
        COUNT(CASE WHEN ptp.status = 'broken' THEN 1 END) as broken_count
    FROM `promises_to_pay` ptp
    JOIN `bookings` bk ON ptp.booking_id = bk.id
    WHERE bk.society_id = :sid
");
$stmtKpi->execute(['sid' => $activeSocId]);
$kpi = $stmtKpi->fetch();

// Fetch Active Bookings for modal dropdown
$stmtBks = $db->prepare("
    SELECT bk.id, bk.booking_number, bk.remaining_balance, c.full_name as customer_name, p.plot_number, b.block_name
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    WHERE bk.society_id = :sid AND bk.status = 'active'
    ORDER BY bk.id DESC
");
$stmtBks->execute(['sid' => $activeSocId]);
$activeBookings = $stmtBks->fetchAll();

// Fetch Active Recovery Officers for dropdown
$stmtOfficers = $db->prepare("
    SELECT ro.id, u.name as officer_name 
    FROM `recovery_officers` ro
    JOIN `users` u ON ro.user_id = u.id
    WHERE ro.society_id = :sid AND ro.status = 'active'
    ORDER BY u.name ASC
");
$stmtOfficers->execute(['sid' => $activeSocId]);
$recoveryOfficers = $stmtOfficers->fetchAll();

$pageTitle = 'Promises to Pay (PTP) Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Promises to Pay (PTP) Tracking</h4>
        <p class="text-muted small mb-0">Follow-up log & commitment tracker for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/recoveries/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-cash-stack me-1"></i> Recovery Desk
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ptpModal">
            <i class="bi bi-plus-circle me-1"></i> Log Promise to Pay
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Pending Commitments</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= (int)$kpi['pending_count'] ?></div>
            <div class="text-primary small fw-semibold"><?= format_currency((float)$kpi['pending_amount']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Overdue Promises (Expired)</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= (int)$kpi['overdue_ptp_count'] ?></div>
            <div class="text-muted small">Target date has passed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Fulfilled Recoveries</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= (int)$kpi['fulfilled_count'] ?></div>
            <div class="text-success small fw-semibold"><?= format_currency((float)$kpi['fulfilled_amount']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Broken Commitments</div>
            <div class="fs-4 fw-bold text-warning mt-1"><?= (int)$kpi['broken_count'] ?></div>
            <div class="text-muted small">Defaulted follow-ups</div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-body">
        <!-- Search and Filter Bar -->
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search buyer, phone, booking or plot..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="fulfilled" <?= ($statusFilter === 'fulfilled') ? 'selected' : '' ?>>Fulfilled</option>
                    <option value="broken" <?= ($statusFilter === 'broken') ? 'selected' : '' ?>>Broken</option>
                    <option value="rescheduled" <?= ($statusFilter === 'rescheduled') ? 'selected' : '' ?>>Rescheduled</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="officer_id" class="form-select form-select-sm">
                    <option value="">All Officers</option>
                    <?php foreach ($recoveryOfficers as $ro): ?>
                        <option value="<?= (int)$ro['id'] ?>" <?= ($officerFilter === (int)$ro['id']) ? 'selected' : '' ?>>
                            <?= e($ro['officer_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/recoveries/promises.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Booking / Plot</th>
                        <th>Buyer Particulars</th>
                        <th>Assigned Officer</th>
                        <th>Contact Date</th>
                        <th>Promise Date</th>
                        <th>Promised Amount</th>
                        <th>Status</th>
                        <th>Follow-up Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promises)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No promises to pay found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($promises as $pRow): ?>
                            <?php
                            $isOverdue = ($pRow['status'] === 'pending' && strtotime($pRow['promise_date']) < strtotime(date('Y-m-d')));
                            ?>
                            <tr class="<?= $isOverdue ? 'table-warning' : '' ?>">
                                <td>
                                    <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$pRow['booking_id']) ?>" class="fw-bold text-decoration-none">
                                        <?= e($pRow['booking_number']) ?>
                                    </a>
                                    <div>
                                        <span class="badge bg-primary"><?= e($pRow['plot_number']) ?></span>
                                        <span class="badge bg-light text-dark border"><?= e($pRow['block_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        <a href="<?= base_url('modules/customers/view.php?id=' . (int)$pRow['customer_id']) ?>" class="text-decoration-none">
                                            <?= e($pRow['customer_name']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-telephone me-1"></i> <?= e($pRow['mobile']) ?>
                                        <?php if (!empty($pRow['whatsapp'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pRow['whatsapp']) ?>?text=Dear%20Customer,%20this%20is%20a%20reminder%20regarding%20your%20payment%20commitment%20for%20Booking%20<?= urlencode($pRow['booking_number']) ?>." target="_blank" class="text-success ms-1" title="Open WhatsApp">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-person me-1"></i> <?= e($pRow['officer_name']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= date('d M Y', strtotime($pRow['contact_date'])) ?></td>
                                <td>
                                    <span class="fw-bold <?= $isOverdue ? 'text-danger' : 'text-dark' ?>">
                                        <?= date('d M Y', strtotime($pRow['promise_date'])) ?>
                                    </span>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-primary">
                                    <?= format_currency($pRow['promised_amount']) ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match ($pRow['status']) {
                                        'pending'     => 'bg-warning text-dark',
                                        'fulfilled'   => 'bg-success',
                                        'broken'      => 'bg-danger',
                                        'rescheduled' => 'bg-info text-dark',
                                        default       => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($pRow['status']) ?></span>
                                </td>
                                <td class="small text-muted" style="max-width: 200px;">
                                    <?= e($pRow['notes'] ?: 'No notes recorded') ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($pRow['status'] === 'pending'): ?>
                                            <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$pRow['booking_id'] . '&payment_amount=' . (float)$pRow['promised_amount']) ?>" class="btn btn-success" title="Collect Payment">
                                                <i class="bi bi-cash me-1"></i> Collect
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this promise as Fulfilled?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="ptp_id" value="<?= (int)$pRow['id'] ?>">
                                                <input type="hidden" name="new_status" value="fulfilled">
                                                <button type="submit" class="btn btn-outline-success" title="Mark Fulfilled">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Mark this promise as Broken?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="ptp_id" value="<?= (int)$pRow['id'] ?>">
                                                <input type="hidden" name="new_status" value="broken">
                                                <button type="submit" class="btn btn-outline-danger" title="Mark Broken">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </form>
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

<!-- Modal: Log Promise to Pay -->
<div class="modal fade" id="ptpModal" tabindex="-1" aria-labelledby="ptpModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_ptp">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="ptpModalLabel"><i class="bi bi-clock-history me-2 text-primary"></i> Log Promise to Pay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Active Booking Contract <span class="text-danger">*</span></label>
                        <select name="booking_id" class="form-select form-select-sm" required>
                            <option value="">Choose Booking Account</option>
                            <?php foreach ($activeBookings as $bk): ?>
                                <option value="<?= (int)$bk['id'] ?>" <?= ($preBookingId === (int)$bk['id']) ? 'selected' : '' ?>>
                                    <?= e($bk['booking_number']) ?> — <?= e($bk['customer_name']) ?> (Plot #<?= e($bk['plot_number']) ?>, <?= e($bk['block_name']) ?>) - Bal: Rs. <?= number_format((float)$bk['remaining_balance'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Assigned Field Recovery Officer <span class="text-danger">*</span></label>
                        <select name="recovery_officer_id" class="form-select form-select-sm" required>
                            <option value="">Choose Recovery Officer</option>
                            <?php foreach ($recoveryOfficers as $ro): ?>
                                <option value="<?= (int)$ro['id'] ?>"><?= e($ro['officer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contact Date</label>
                            <input type="date" name="contact_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Promised Pay Date <span class="text-danger">*</span></label>
                            <input type="date" name="promise_date" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Promised Payment Amount (Rs.) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="promised_amount" class="form-control form-control-sm" required placeholder="e.g. 150000.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Follow-up Notes / Customer Conversation</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="e.g. Spoke with buyer. He requested 1 week grace period due to salary delay; promised to pay Rs. 150,000 via online bank transfer."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">Save Commitment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($preBookingId > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('ptpModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
