<?php
/**
 * HSMS - Official Plot Cancellation Order & Refund Advice (Print View)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('bookings_cancel');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$cancellationId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT cnl.*,
           c.full_name as customer_name, c.father_husband_name, c.cnic_passport, c.mobile, c.residential_address as address,
           p.plot_number, p.is_corner, p.is_boulevard, p.is_park_facing, ps.name as size_name, pt.name as type_name,
           b.block_name, ph.phase_name,
           bk.booking_number, bk.booking_date, bk.net_sale_price,
           u.name as approved_by_name
    FROM `cancellations` cnl
    JOIN `plots` p ON cnl.plot_id = p.id
    LEFT JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    LEFT JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    JOIN `bookings` bk ON cnl.booking_id = bk.id
    JOIN `customers` c ON cnl.customer_id = c.id
    JOIN `users` u ON cnl.approved_by = u.id
    WHERE cnl.id = :id AND bk.society_id = :sid
");
$stmt->execute(['id' => $cancellationId, 'sid' => $activeSocId]);
$cnl = $stmt->fetch();

if (!$cnl) {
    die("Cancellation record not found or unauthorized access.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation Order — <?= e($cnl['cancellation_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
        }
        .order-container {
            max-width: 800px;
            margin: 25px auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 2px solid #dc2626;
            position: relative;
        }
        .order-border {
            border: 1px dashed #ef4444;
            padding: 25px;
        }
        .order-header {
            text-align: center;
            border-bottom: 2px double #dc2626;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .order-title {
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 10px;
            color: #dc2626;
        }
        .section-heading {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #fef2f2;
            padding: 4px 10px;
            border-left: 3px solid #dc2626;
            margin: 15px 0 10px 0;
        }
        .field-label {
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .field-val {
            font-weight: 600;
            color: #0f172a;
        }
        .legal-clause {
            font-size: 11px;
            line-height: 1.6;
            text-align: justify;
            background: #fff5f5;
            border: 1px solid #fecaca;
            padding: 12px;
            margin: 20px 0;
        }
        .sig-box {
            text-align: center;
            margin-top: 50px;
        }
        .sig-line {
            border-top: 1px solid #0f172a;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: 600;
            font-size: 11px;
        }
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .order-container { box-shadow: none; border: 2px solid #dc2626; margin: 0; width: 100%; max-width: 100%; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center my-3 no-print">
    <button onclick="window.print()" class="btn btn-danger px-4 py-2 fw-semibold">
        <i class="bi bi-printer me-1"></i> Print Cancellation Order
    </button>
    <a href="<?= base_url('modules/cancellations/index.php') ?>" class="btn btn-outline-secondary px-4 py-2 ms-2">
        Back to Register
    </a>
</div>

<div class="order-container">
    <div class="order-border">
        <!-- Header -->
        <div class="order-header">
            <h3 class="fw-bold mb-0 text-uppercase"><?= e($society['name'] ?? 'Housing Society') ?></h3>
            <p class="text-muted small mb-1"><?= e($society['address'] ?? 'Head Office') ?> | Tel: <?= e($society['contact_number'] ?? 'N/A') ?></p>
            <div class="order-title">Official Order of Allotment Cancellation</div>
            <div class="d-flex justify-content-between mt-3 small fw-semibold">
                <span>ORDER REF: <strong class="text-danger"><?= e($cnl['cancellation_number']) ?></strong></span>
                <span>EFFECTIVE DATE: <strong><?= date('d F Y', strtotime($cnl['cancellation_date'])) ?></strong></span>
            </div>
        </div>

        <!-- 1. Allottee Particulars -->
        <div class="section-heading">1. Former Allottee Particulars</div>
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="field-label">Customer Name</div>
                <div class="field-val"><?= e($cnl['customer_name']) ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">Father / Husband Name</div>
                <div class="field-val"><?= e($cnl['father_husband_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">CNIC / Passport #</div>
                <div class="field-val"><?= e($cnl['cnic_passport']) ?></div>
            </div>
            <div class="col-8">
                <div class="field-label">Address on Record</div>
                <div class="field-val"><?= e($cnl['address'] ?: 'On Record') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">Mobile Contact</div>
                <div class="field-val"><?= e($cnl['mobile']) ?></div>
            </div>
        </div>

        <!-- 2. Cancelled Plot Specification -->
        <div class="section-heading">2. Cancelled Property Specification</div>
        <div class="row g-2 mb-3">
            <div class="col-3">
                <div class="field-label">Plot Number</div>
                <div class="field-val fs-6 text-danger"><?= e($cnl['plot_number']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Sector / Block</div>
                <div class="field-val"><?= e($cnl['block_name']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Phase</div>
                <div class="field-val"><?= e($cnl['phase_name']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Size / Category</div>
                <div class="field-val"><?= e($cnl['size_name'] ?: '5 Marla') ?> (<?= e($cnl['type_name'] ?: 'Residential') ?>)</div>
            </div>
        </div>

        <!-- 3. Financial Breakdown & Refund Settlement -->
        <div class="section-heading">3. Financial Settlement & Refund Advice</div>
        <table class="table table-bordered table-sm mb-3">
            <thead class="table-light">
                <tr>
                    <th>Original Booking Ref</th>
                    <th>Net Sale Price</th>
                    <th>Total Payments Deposited</th>
                    <th>Society Retention / Penalty</th>
                    <th>Net Refund Payable</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fw-semibold">
                    <td><?= e($cnl['booking_number']) ?></td>
                    <td><?= format_currency($cnl['net_sale_price']) ?></td>
                    <td class="text-success"><?= format_currency($cnl['total_paid_at_cancel']) ?></td>
                    <td class="text-danger">- <?= format_currency($cnl['deduction_amount']) ?></td>
                    <td class="text-primary fs-6"><?= format_currency($cnl['refund_amount_payable']) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- 4. Official Grounds -->
        <div class="section-heading">4. Grounds for Cancellation</div>
        <div class="p-2 border rounded bg-light mb-3">
            <strong>Stated Grounds:</strong> <?= nl2br(e($cnl['reason'])) ?>
        </div>

        <!-- Legal Statement -->
        <div class="legal-clause">
            <strong>OFFICIAL NOTICE:</strong><br>
            By virtue of this order, Allotment Booking #<strong><?= e($cnl['booking_number']) ?></strong> stands definitively terminated. The rights of allotment for Plot #<strong><?= e($cnl['plot_number']) ?></strong> have reverted unconditionally to the society and the unit has been reinstated into available inventory. Net refund of <strong>Rs. <?= number_format((float)$cnl['refund_amount_payable'], 2) ?></strong> shall be disbursed per standard financial disbursement protocols.
        </div>

        <!-- Signatures -->
        <div class="row pt-4">
            <div class="col-4 sig-box">
                <div class="sig-line">HEAD OF ACCOUNTS<br><span class="text-muted" style="font-size: 10px;">Audit & Verification</span></div>
            </div>
            <div class="col-4 sig-box">
                <div class="sig-line">MANAGING DIRECTOR<br><span class="text-muted" style="font-size: 10px;">Executive Approval</span></div>
            </div>
            <div class="col-4 sig-box">
                <div class="sig-line">FORMER ALLOTTEE<br><span class="text-muted" style="font-size: 10px;">Acknowledgment of Settlement</span></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
