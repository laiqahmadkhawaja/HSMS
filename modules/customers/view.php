<?php
/**
 * HSMS - Customer 360-Degree Profile & Financial Ledger
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('customers_manage');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM `customers` WHERE `id` = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect_with_message(base_url('modules/customers/index.php'), 'Customer not found.', 'danger');
}

// 1. Fetch All Bookings of this Customer
$stmtBk = $db->prepare("
    SELECT bk.*, p.plot_number, p.street_road, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name
    FROM `bookings` bk
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    WHERE bk.customer_id = :cid
    ORDER BY bk.id DESC
");
$stmtBk->execute(['cid' => $id]);
$bookings = $stmtBk->fetchAll();

// 2. Aggregate Financial Position
$totalSale = 0.0;
$totalPaid = 0.0;
$totalOutstanding = 0.0;
$totalAdvance = 0.0;
foreach ($bookings as $bk) {
    if ($bk['status'] === 'active') {
        $totalSale += (float)$bk['net_sale_price'];
        $totalPaid += (float)$bk['total_paid'];
        $totalOutstanding += (float)$bk['remaining_balance'];
        $totalAdvance += (float)$bk['advance_balance'];
    }
}

// 3. Overdue Dues across all customer bookings
$stmtOverdue = $db->prepare("
    SELECT s.*, bk.booking_number, p.plot_number
    FROM `installment_schedules` s
    JOIN `bookings` bk ON s.booking_id = bk.id
    JOIN `plots` p ON bk.plot_id = p.id
    WHERE bk.customer_id = :cid AND bk.status = 'active'
      AND s.status IN ('pending', 'partially_paid') AND s.due_date < CURDATE()
    ORDER BY s.due_date ASC
");
$stmtOverdue->execute(['cid' => $id]);
$overdueInstallments = $stmtOverdue->fetchAll();

$totalOverdueAmount = 0.0;
foreach ($overdueInstallments as $od) {
    $totalOverdueAmount += (float)$od['balance_due'];
}

// 4. Fetch Receipts History
$stmtPay = $db->prepare("
    SELECT pay.*, bk.booking_number, p.plot_number, acc.account_name
    FROM `payments` pay
    JOIN `bookings` bk ON pay.booking_id = bk.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `accounts` acc ON pay.cash_bank_account_id = acc.id
    WHERE pay.customer_id = :cid
    ORDER BY pay.id DESC
");
$stmtPay->execute(['cid' => $id]);
$payments = $stmtPay->fetchAll();

// 5. Fetch Customer Ledger
$stmtLedger = $db->prepare("
    SELECT cl.*, p.plot_number, bk.booking_number
    FROM `customer_ledger` cl
    JOIN `plots` p ON cl.plot_id = p.id
    JOIN `bookings` bk ON cl.booking_id = bk.id
    WHERE cl.customer_id = :cid
    ORDER BY cl.transaction_date ASC, cl.id ASC
");
$stmtLedger->execute(['cid' => $id]);
$ledgerEntries = $stmtLedger->fetchAll();

// 6. Fetch KYC Documents
$stmtDocs = $db->prepare("SELECT * FROM `customer_documents` WHERE `customer_id` = :cid ORDER BY id DESC");
$stmtDocs->execute(['cid' => $id]);
$documents = $stmtDocs->fetchAll();

$pageTitle = 'Customer 360: ' . $customer['full_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">
            <?= e($customer['full_name']) ?>
            <span class="badge bg-light text-primary border ms-2 fs-6"><?= e($customer['customer_id_code']) ?></span>
            <span class="badge <?= ($customer['status'] === 'active') ? 'bg-success' : 'bg-secondary' ?> fs-6 ms-1">
                <?= ucfirst(e($customer['status'])) ?>
            </span>
        </h4>
        <p class="text-muted small mb-0">CNIC: <strong><?= e($customer['cnic_passport']) ?></strong> | Mobile: <strong><?= e($customer['mobile']) ?></strong> | City: <?= e($customer['city']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="<?= base_url('modules/customers/edit.php?id=' . $id) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Profile
        </a>
        <a href="<?= base_url('modules/bookings/create.php?customer_id=' . $id) ?>" class="btn btn-hs-primary btn-sm">
            <i class="bi bi-cart-plus me-1"></i> New Booking
        </a>
    </div>
</div>

<!-- Financial Position Summary KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Total Contract Value</div>
                <div class="metric-value"><?= format_currency($totalSale) ?></div>
                <div class="small text-muted mt-1"><?= count($bookings) ?> Plot(s) in portfolio</div>
            </div>
            <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-card-checklist"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Total Amount Paid</div>
                <div class="metric-value text-success"><?= format_currency($totalPaid) ?></div>
                <div class="small text-muted mt-1">Cleared across all plots</div>
            </div>
            <div class="metric-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Remaining Balance</div>
                <div class="metric-value text-dark"><?= format_currency($totalOutstanding) ?></div>
                <div class="small text-muted mt-1">Total future receivables</div>
            </div>
            <div class="metric-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Overdue Outstanding</div>
                <div class="metric-value text-danger"><?= format_currency($totalOverdueAmount) ?></div>
                <div class="small text-muted mt-1">
                    <span class="fw-bold text-danger"><?= count($overdueInstallments) ?></span> Due installment(s)
                </div>
            </div>
            <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Profile Info Card -->
<div class="card-hs mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2 text-center border-end">
                <?php if (!empty($customer['photo_path']) && file_exists(BASE_PATH . '/' . $customer['photo_path'])): ?>
                    <img src="<?= base_url($customer['photo_path']) ?>" alt="Photo" class="rounded-circle img-thumbnail mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary bg-opacity-10 text-secondary d-inline-flex align-items-center justify-content-center mb-2" style="width: 90px; height: 90px; font-size: 2.5rem;">
                        <i class="bi bi-person"></i>
                    </div>
                <?php endif; ?>
                <div class="small fw-semibold"><?= e($customer['full_name']) ?></div>
                <div class="text-muted" style="font-size: 0.75rem;">Member since <?= format_date($customer['created_at']) ?></div>
            </div>
            <div class="col-md-5">
                <h6 class="fw-bold text-primary small text-uppercase mb-2">Personal & Contact Info</h6>
                <div class="row g-2 small">
                    <div class="col-4 text-muted">Father / Husband:</div>
                    <div class="col-8 fw-semibold"><?= e($customer['father_husband_name']) ?></div>
                    <div class="col-4 text-muted">CNIC:</div>
                    <div class="col-8"><?= e($customer['cnic_passport']) ?></div>
                    <div class="col-4 text-muted">Mobile:</div>
                    <div class="col-8"><?= e($customer['mobile']) ?></div>
                    <div class="col-4 text-muted">WhatsApp:</div>
                    <div class="col-8"><?= e($customer['whatsapp'] ?: '—') ?></div>
                    <div class="col-4 text-muted">Email:</div>
                    <div class="col-8"><?= e($customer['email'] ?: '—') ?></div>
                    <div class="col-4 text-muted">Address:</div>
                    <div class="col-8"><?= e($customer['residential_address']) ?>, <?= e($customer['city']) ?></div>
                </div>
            </div>
            <div class="col-md-5 border-start">
                <h6 class="fw-bold text-primary small text-uppercase mb-2">Nominee / Next of Kin</h6>
                <div class="row g-2 small">
                    <div class="col-4 text-muted">Nominee Name:</div>
                    <div class="col-8 fw-semibold"><?= e($customer['nominee_name'] ?: 'Not recorded') ?></div>
                    <div class="col-4 text-muted">Relation:</div>
                    <div class="col-8"><?= e($customer['nominee_relation'] ?: '—') ?></div>
                    <div class="col-4 text-muted">Nominee CNIC:</div>
                    <div class="col-8"><?= e($customer['nominee_cnic'] ?: '—') ?></div>
                    <div class="col-4 text-muted">Contact:</div>
                    <div class="col-8"><?= e($customer['nominee_contact'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" id="customerTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="plots-tab" data-bs-toggle="tab" data-bs-target="#tab-plots" type="button" role="tab">
            <i class="bi bi-geo-alt me-1"></i> Booked Plots (<?= count($bookings) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#tab-overdue" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Overdue Dues (<?= count($overdueInstallments) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="receipts-tab" data-bs-toggle="tab" data-bs-target="#tab-receipts" type="button" role="tab">
            <i class="bi bi-receipt me-1"></i> Receipts & Payments (<?= count($payments) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#tab-ledger" type="button" role="tab">
            <i class="bi bi-journal-text me-1"></i> Financial Statement / Ledger (<?= count($ledgerEntries) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="docs-tab" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
            <i class="bi bi-file-earmark-check me-1"></i> KYC Documents (<?= count($documents) ?>)
        </button>
    </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="customerTabContent">
    <!-- Tab 1: Booked Plots -->
    <div class="tab-pane fade show active" id="tab-plots" role="tabpanel">
        <div class="card-hs">
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Plot #</th>
                            <th>Phase & Block</th>
                            <th>Size & Type</th>
                            <th>Net Price</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No plot bookings found for this customer.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $bk): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$bk['id']) ?>" class="fw-bold text-decoration-none">
                                            <?= e($bk['booking_number']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= format_date($bk['booking_date']) ?></div>
                                    </td>
                                    <td><span class="badge bg-primary fs-6"><?= e($bk['plot_number']) ?></span></td>
                                    <td><?= e($bk['phase_name']) ?> / <strong><?= e($bk['block_name']) ?></strong></td>
                                    <td><?= e($bk['size_name']) ?> <span class="text-muted">(<?= e($bk['type_name']) ?>)</span></td>
                                    <td><?= format_currency($bk['net_sale_price']) ?></td>
                                    <td class="text-success fw-semibold"><?= format_currency($bk['total_paid']) ?></td>
                                    <td class="text-danger fw-bold"><?= format_currency($bk['remaining_balance']) ?></td>
                                    <td><span class="status-badge badge-<?= e($bk['status']) ?>"><?= ucfirst(e($bk['status'])) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$bk['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                                            View Schedule
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

    <!-- Tab 2: Overdue Installments -->
    <div class="tab-pane fade" id="tab-overdue" role="tabpanel">
        <div class="card-hs">
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Plot #</th>
                            <th>Installment Title</th>
                            <th>Due Date</th>
                            <th>Total Due</th>
                            <th>Paid Amount</th>
                            <th>Overdue Balance</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overdueInstallments)): ?>
                            <tr><td colspan="8" class="text-center text-success py-4"><i class="bi bi-check-circle me-1"></i> No overdue installments. All payments are up to date!</td></tr>
                        <?php else: ?>
                            <?php foreach ($overdueInstallments as $od): ?>
                                <tr>
                                    <td><?= e($od['booking_number']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($od['plot_number']) ?></span></td>
                                    <td class="fw-semibold"><?= e($od['installment_title']) ?></td>
                                    <td class="text-danger fw-bold"><?= format_date($od['due_date']) ?></td>
                                    <td><?= format_currency($od['amount_due']) ?></td>
                                    <td><?= format_currency($od['amount_paid']) ?></td>
                                    <td class="text-danger fw-bold"><?= format_currency($od['balance_due']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('modules/recoveries/create.php?booking_id=' . (int)$od['booking_id']) ?>" class="btn btn-sm btn-success py-0 px-2">
                                            Pay Now
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

    <!-- Tab 3: Receipts & Payments -->
    <div class="tab-pane fade" id="tab-receipts" role="tabpanel">
        <div class="card-hs">
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date</th>
                            <th>Booking #</th>
                            <th>Plot #</th>
                            <th>Amount</th>
                            <th>Allocated</th>
                            <th>Advance</th>
                            <th>Method</th>
                            <th class="text-end">Print</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No payment receipts collected yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$pay['id']) ?>" target="_blank" class="fw-bold text-decoration-none">
                                            <?= e($pay['receipt_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= format_date($pay['payment_date']) ?></td>
                                    <td><?= e($pay['booking_number']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($pay['plot_number']) ?></span></td>
                                    <td class="fw-bold text-success"><?= format_currency($pay['payment_amount']) ?></td>
                                    <td><?= format_currency($pay['allocated_amount']) ?></td>
                                    <td class="text-primary"><?= format_currency($pay['advance_amount']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst(e($pay['payment_method'])) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$pay['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Print Receipt">
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
    </div>

    <!-- Tab 4: Financial Ledger -->
    <div class="tab-pane fade" id="tab-ledger" role="tabpanel">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-1"></i> Complete Financial Running Statement</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Statement
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher / Ref</th>
                            <th>Plot</th>
                            <th>Description</th>
                            <th class="text-end">Debit (Due)</th>
                            <th class="text-end">Credit (Paid)</th>
                            <th class="text-end">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ledgerEntries)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No financial transactions recorded on customer ledger.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledgerEntries as $le): ?>
                                <tr>
                                    <td><?= format_date($le['transaction_date']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= e($le['voucher_no'] ?: '—') ?></span></td>
                                    <td><span class="badge bg-secondary"><?= e($le['plot_number']) ?></span></td>
                                    <td><?= e($le['description']) ?></td>
                                    <td class="text-end fw-semibold <?= ((float)$le['debit'] > 0) ? 'text-dark' : 'text-muted' ?>">
                                        <?= ((float)$le['debit'] > 0) ? format_currency($le['debit']) : '—' ?>
                                    </td>
                                    <td class="text-end fw-semibold <?= ((float)$le['credit'] > 0) ? 'text-success' : 'text-muted' ?>">
                                        <?= ((float)$le['credit'] > 0) ? format_currency($le['credit']) : '—' ?>
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        <?= format_currency($le['running_balance']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 5: KYC Documents -->
    <div class="tab-pane fade" id="tab-docs" role="tabpanel">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip me-1"></i> KYC & Legal Document Attachments</span>
                <button class="btn btn-sm btn-hs-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload Document
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-folder2-open fs-1 d-block mb-2"></i>
                        No KYC documents uploaded yet.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
                                        <div>
                                            <div class="fw-bold small"><?= e($doc['doc_type']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= e($doc['file_name']) ?> (<?= format_date($doc['uploaded_at']) ?>)</div>
                                        </div>
                                    </div>
                                    <a href="<?= base_url($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" title="View Document">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= base_url('modules/customers/upload_doc.php') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="customer_id" value="<?= $id ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Upload KYC Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select name="doc_type" class="form-select form-select-sm" required>
                            <option value="CNIC Front">CNIC Front</option>
                            <option value="CNIC Back">CNIC Back</option>
                            <option value="Nominee CNIC">Nominee CNIC</option>
                            <option value="Affidavit / Undertaking">Affidavit / Undertaking</option>
                            <option value="Signature Specimen">Signature Specimen</option>
                            <option value="Allotment Letter Copy">Allotment Letter Copy</option>
                            <option value="Other">Other Document</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select File (PDF, PNG, JPG, WEBP - Max 5MB) <span class="text-danger">*</span></label>
                        <input type="file" name="doc_file" class="form-control form-control-sm" accept=".pdf,image/jpeg,image/png,image/webp" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-hs-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
