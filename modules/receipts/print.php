<?php
/**
 * HSMS - Multi-Layout Payment Receipt Printer (A4, 80mm POS Thermal, 58mm POS Thermal)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('payments_collect');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$layout = strtolower(trim($_GET['layout'] ?? 'a4'));
if (!in_array($layout, ['a4', 'thermal80', 'thermal58'], true)) {
    $layout = 'a4';
}

$stmt = $db->prepare("
    SELECT pay.*, bk.booking_number, bk.net_sale_price, bk.total_paid as current_total_paid, bk.remaining_balance as current_remaining_bal,
           c.full_name as customer_name, c.customer_id_code, c.cnic_passport, c.mobile, c.residential_address,
           p.plot_number, b.block_name, ph.phase_name, pt.name as type_name, ps.name as size_name,
           s.name as society_name, s.society_code, s.address as society_address, s.contact_number as society_contact,
           acc.account_name, u.name as receiver_name
    FROM `payments` pay
    JOIN `bookings` bk ON pay.booking_id = bk.id
    JOIN `customers` c ON pay.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    JOIN `societies` s ON pay.society_id = s.id
    JOIN `accounts` acc ON pay.cash_bank_account_id = acc.id
    JOIN `users` u ON pay.created_by = u.id
    WHERE pay.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$pay = $stmt->fetch();

if (!$pay) {
    die("Payment Receipt not found.");
}

// Fetch allocations details
$stmtAlloc = $db->prepare("
    SELECT pa.*, s.installment_no, s.installment_title, s.due_date
    FROM `payment_allocations` pa
    JOIN `installment_schedules` s ON pa.schedule_id = s.id
    WHERE pa.payment_id = :pid
    ORDER BY s.installment_no ASC
");
$stmtAlloc->execute(['pid' => $id]);
$allocations = $stmtAlloc->fetchAll();

// Security verification hash code
$verificationCode = strtoupper(substr(hash('sha256', $pay['receipt_number'] . $pay['payment_amount'] . $pay['payment_date']), 0, 12));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — <?= e($pay['receipt_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* A4 Layout Styling */
        .layout-a4 {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        /* 80mm Thermal Slip Styling */
        .layout-thermal80 {
            width: 80mm;
            padding: 4mm;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #94a3b8;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
        }

        /* 58mm Thermal Slip Styling */
        .layout-thermal58 {
            width: 58mm;
            padding: 3mm;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #94a3b8;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            line-height: 1.2;
        }

        .border-dashed {
            border-bottom: 1px dashed #64748b;
            margin: 8px 0;
        }

        @media print {
            body { background: transparent; }
            .no-print { display: none !important; }
            .layout-a4, .layout-thermal80, .layout-thermal58 {
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>

<!-- Top Controls Toolbar -->
<div class="container py-3 text-center no-print">
    <div class="d-inline-flex gap-2 p-2 bg-white rounded border shadow-sm">
        <a href="<?= base_url('modules/receipts/print.php?id=' . $id . '&layout=a4') ?>" class="btn btn-sm <?= ($layout === 'a4') ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-file-earmark-text me-1"></i> A4 Standard
        </a>
        <a href="<?= base_url('modules/receipts/print.php?id=' . $id . '&layout=thermal80') ?>" class="btn btn-sm <?= ($layout === 'thermal80') ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-receipt me-1"></i> 80mm Thermal
        </a>
        <a href="<?= base_url('modules/receipts/print.php?id=' . $id . '&layout=thermal58') ?>" class="btn btn-sm <?= ($layout === 'thermal58') ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-receipt-cutoff me-1"></i> 58mm Thermal
        </a>
        <button onclick="window.print()" class="btn btn-sm btn-success px-3 ms-3">
            <i class="bi bi-printer me-1"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="btn btn-sm btn-secondary">
            Close
        </button>
    </div>
</div>

<?php if ($layout === 'a4'): ?>
    <!-- A4 STANDARD FORMAL VOUCHER -->
    <div class="layout-a4">
        <!-- Receipt Header -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
            <div>
                <h3 class="fw-bold mb-0 text-primary"><?= e($pay['society_name']) ?></h3>
                <div class="small text-muted"><?= e($pay['society_address']) ?></div>
                <div class="small text-muted">Phone: <?= e($pay['society_contact']) ?></div>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5 text-dark"><?= e($pay['receipt_number']) ?></div>
                <div class="small text-muted">Date: <?= format_date($pay['payment_date']) ?></div>
                <?php if ($pay['status'] === 'reversed'): ?>
                    <span class="badge bg-danger">REVERSED RECEIPT</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center py-1 mb-3 bg-light border rounded">
            <h5 class="fw-bold mb-0 text-uppercase text-secondary" style="letter-spacing: 0.05em;">Payment Collection Receipt</h5>
        </div>

        <!-- Particulars Grid -->
        <div class="row g-3 mb-3 small">
            <div class="col-6">
                <div class="p-2 border rounded bg-white">
                    <div class="text-muted">Received From (Member):</div>
                    <div class="fw-bold fs-6"><?= e($pay['customer_name']) ?> (<?= e($pay['customer_id_code']) ?>)</div>
                    <div>CNIC: <?= e($pay['cnic_passport']) ?> | Mobile: <?= e($pay['mobile']) ?></div>
                    <div>Address: <?= e($pay['residential_address']) ?></div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 border rounded bg-white">
                    <div class="text-muted">Allotted Property Particulars:</div>
                    <div class="fw-bold fs-6 text-primary">Plot #<?= e($pay['plot_number']) ?> — <?= e($pay['block_name']) ?></div>
                    <div>Sector / Phase: <?= e($pay['phase_name']) ?></div>
                    <div>Size & Category: <?= e($pay['size_name']) ?> (<?= e($pay['type_name']) ?>)</div>
                    <div>Booking Contract #: <strong><?= e($pay['booking_number']) ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Payment Meta -->
        <table class="table table-sm table-bordered mb-3 small">
            <tr class="bg-light">
                <th style="width: 25%;">Payment Method:</th>
                <td style="width: 25%;"><span class="fw-bold text-uppercase"><?= e($pay['payment_method']) ?></span></td>
                <th style="width: 25%;">Deposited To Account:</th>
                <td style="width: 25%;"><?= e($pay['account_name']) ?></td>
            </tr>
            <?php if (!empty($pay['transaction_ref'])): ?>
                <tr>
                    <th>Cheque / Ref #:</th>
                    <td colspan="3"><strong><?= e($pay['transaction_ref']) ?></strong></td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Allocations Table -->
        <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-list-check me-1"></i> Installment Allocation Breakdown</h6>
        <table class="table table-sm table-bordered mb-3 small">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Installment Description</th>
                    <th>Due Date</th>
                    <th class="text-end">Amount Allocated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allocations)): ?>
                    <tr><td colspan="4" class="text-center text-muted">Payment credited entirely to customer advance.</td></tr>
                <?php else: ?>
                    <?php foreach ($allocations as $al): ?>
                        <tr>
                            <td><?= (int)$al['installment_no'] ?></td>
                            <td><?= e($al['installment_title']) ?></td>
                            <td><?= format_date($al['due_date']) ?></td>
                            <td class="text-end fw-semibold"><?= format_currency($al['allocated_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ((float)$pay['advance_amount'] > 0): ?>
                    <tr class="table-info">
                        <td colspan="3" class="fw-bold">Unallocated Customer Advance Balance:</td>
                        <td class="text-end fw-bold"><?= format_currency($pay['advance_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="table-light">
                    <td colspan="3" class="fw-bold fs-6 text-end">Total Payment Collected:</td>
                    <td class="text-end fw-bold fs-6 text-success"><?= format_currency($pay['payment_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Balance Status -->
        <div class="row g-2 small p-2 bg-light rounded border mb-4">
            <div class="col-4">
                <span class="text-muted">Total Contract Value:</span>
                <span class="fw-bold ms-1"><?= format_currency($pay['net_sale_price']) ?></span>
            </div>
            <div class="col-4 text-center">
                <span class="text-muted">Total Cleared to Date:</span>
                <span class="fw-bold text-success ms-1"><?= format_currency($pay['current_total_paid']) ?></span>
            </div>
            <div class="col-4 text-end">
                <span class="text-muted">Remaining Balance:</span>
                <span class="fw-bold text-danger ms-1"><?= format_currency($pay['current_remaining_bal']) ?></span>
            </div>
        </div>

        <!-- Footer & Signatures -->
        <div class="row pt-5 small">
            <div class="col-4 text-center">
                <div class="border-top pt-2">Depositor / Member Signature</div>
            </div>
            <div class="col-4 text-center">
                <div class="border-top pt-2">Received By: <strong><?= e($pay['receiver_name']) ?></strong></div>
            </div>
            <div class="col-4 text-center">
                <div class="border-top pt-2">Authorized Accounts Directorate</div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
            <div>Verification Code: <code><?= e($verificationCode) ?></code> | System Generated Electronic Receipt</div>
            <div>Printed on: <?= date('d M Y, h:i A') ?></div>
        </div>
    </div>

<?php elseif ($layout === 'thermal80' || $layout === 'thermal58'): ?>
    <!-- 80mm / 58mm THERMAL POS SLIP -->
    <div class="<?= ($layout === 'thermal80') ? 'layout-thermal80' : 'layout-thermal58' ?>">
        <div class="text-center">
            <div class="fw-bold" style="font-size: 13px;"><?= e($pay['society_name']) ?></div>
            <div><?= e($pay['society_contact']) ?></div>
            <div class="border-dashed"></div>
            <div class="fw-bold">PAYMENT RECEIPT</div>
            <div><?= e($pay['receipt_number']) ?></div>
            <div><?= date('d-m-Y H:i', strtotime($pay['created_at'])) ?></div>
            <div class="border-dashed"></div>
        </div>

        <div><strong>Buyer:</strong> <?= e($pay['customer_name']) ?></div>
        <div><strong>CNIC:</strong> <?= e($pay['cnic_passport']) ?></div>
        <div><strong>Plot:</strong> <?= e($pay['plot_number']) ?> (<?= e($pay['block_name']) ?>)</div>
        <div><strong>Bk #:</strong> <?= e($pay['booking_number']) ?></div>
        <div class="border-dashed"></div>

        <div><strong>Method:</strong> <?= strtoupper(e($pay['payment_method'])) ?></div>
        <div><strong>Account:</strong> <?= e($pay['account_name']) ?></div>
        <?php if (!empty($pay['transaction_ref'])): ?>
            <div><strong>Ref:</strong> <?= e($pay['transaction_ref']) ?></div>
        <?php endif; ?>
        <div class="border-dashed"></div>

        <div class="fw-bold mb-1">ALLOCATIONS:</div>
        <?php foreach ($allocations as $al): ?>
            <div class="d-flex justify-content-between">
                <span>#<?= $al['installment_no'] ?>: <?= substr($al['installment_title'], 0, 16) ?></span>
                <span><?= format_currency($al['allocated_amount'], 2, false) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ((float)$pay['advance_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Customer Advance:</span>
                <span><?= format_currency($pay['advance_amount'], 2, false) ?></span>
            </div>
        <?php endif; ?>

        <div class="border-dashed"></div>
        <div class="d-flex justify-content-between fw-bold fs-6">
            <span>TOTAL PAID:</span>
            <span>Rs. <?= number_format((float)$pay['payment_amount'], 2) ?></span>
        </div>
        <div class="border-dashed"></div>

        <div>Remaining Bal: Rs. <?= number_format((float)$pay['current_remaining_bal'], 2) ?></div>
        <div>Received By: <?= e($pay['receiver_name']) ?></div>
        <div class="border-dashed"></div>

        <div class="text-center" style="font-size: 9px;">
            <div>Code: <?= e($verificationCode) ?></div>
            <div>Thank you for your prompt payment!</div>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
