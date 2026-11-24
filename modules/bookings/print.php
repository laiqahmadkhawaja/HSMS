<?php
/**
 * HSMS - Printable Plot Allotment Order & Contract Certificate
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('bookings_view');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$activeSocId = get_active_society_id();

$stmt = $db->prepare("
    SELECT bk.*, c.full_name as customer_name, c.customer_id_code, c.cnic_passport, c.mobile, c.residential_address, c.city,
           c.nominee_name, c.nominee_relation, c.nominee_cnic,
           p.plot_number, p.street_road, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name, ps.dimension_details,
           s.name as society_name, s.society_code, s.location as society_location, s.address as society_address,
           s.contact_number as society_contact, s.email as society_email, s.registration_number as society_reg_no
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    JOIN `plots` p ON bk.plot_id = p.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    JOIN `societies` s ON bk.society_id = s.id
    WHERE bk.id = :id AND bk.society_id = :sid
    LIMIT 1
");
$stmt->execute(['id' => $id, 'sid' => $activeSocId]);
$b = $stmt->fetch();

if (!$b) {
    die("Allotment Order not found or access restricted.");
}

$stmtSched = $db->prepare("SELECT * FROM `installment_schedules` WHERE `booking_id` = :bid ORDER BY `installment_no` ASC");
$stmtSched->execute(['bid' => $id]);
$schedules = $stmtSched->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Allotment Order — <?= e($b['booking_number']) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Times New Roman', Times, serif;
            color: #0f172a;
        }
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .org-header {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cert-title {
            text-align: center;
            font-size: 1.4rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1e3a8a;
            margin-bottom: 15px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 8px;
        }
        .section-label {
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            background: #f8fafc;
            padding: 4px 8px;
            border-left: 3px solid #1e3a8a;
            margin-bottom: 8px;
        }
        .meta-table td {
            padding: 4px 8px;
            font-size: 0.88rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .meta-table td.label {
            font-weight: bold;
            color: #475569;
            width: 25%;
        }
        .sign-box {
            border-top: 1px solid #94a3b8;
            text-align: center;
            font-size: 0.82rem;
            padding-top: 6px;
            margin-top: 50px;
        }
        @media print {
            body { background: transparent; }
            .page-sheet {
                border: none;
                box-shadow: none;
                margin: 0;
                width: 100%;
                padding: 10mm;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center my-3 no-print">
    <button onclick="window.print()" class="btn btn-primary px-4 fw-bold me-2">
        Print Allotment Order
    </button>
    <button onclick="window.close()" class="btn btn-secondary px-3">
        Close Window
    </button>
</div>

<div class="page-sheet">
    <!-- Header -->
    <div class="org-header">
        <div>
            <h3 class="fw-bold mb-0 text-primary"><?= e($b['society_name']) ?></h3>
            <div class="small text-muted"><?= e($b['society_address']) ?>, <?= e($b['society_location']) ?></div>
            <div class="small text-muted">Phone: <?= e($b['society_contact']) ?> | Reg / NOC: <?= e($b['society_reg_no'] ?: 'Approved') ?></div>
        </div>
        <div class="text-end">
            <span class="badge bg-primary fs-6"><?= e($b['society_code']) ?></span>
            <div class="fw-bold mt-1" style="font-size: 0.95rem;"><?= e($b['booking_number']) ?></div>
            <div class="text-muted small">Date: <?= format_date($b['booking_date']) ?></div>
        </div>
    </div>

    <div class="cert-title">Provisional Allotment Order & Contract</div>

    <!-- Applicant Particulars -->
    <div class="section-label">1. Applicant / Allottee Particulars</div>
    <table class="table meta-table w-100 mb-3">
        <tr>
            <td class="label">Member / Allottee:</td>
            <td><strong><?= e($b['customer_name']) ?></strong></td>
            <td class="label">Customer Code:</td>
            <td><?= e($b['customer_id_code']) ?></td>
        </tr>
        <tr>
            <td class="label">Father / Husband:</td>
            <td><?= e($b['father_husband_name'] ?? '—') ?></td>
            <td class="label">CNIC / Passport:</td>
            <td><strong><?= e($b['cnic_passport']) ?></strong></td>
        </tr>
        <tr>
            <td class="label">Mobile Number:</td>
            <td><?= e($b['mobile']) ?></td>
            <td class="label">City / Country:</td>
            <td><?= e($b['city']) ?>, Pakistan</td>
        </tr>
        <tr>
            <td class="label">Postal Address:</td>
            <td colspan="3"><?= e($b['residential_address']) ?></td>
        </tr>
        <tr>
            <td class="label">Nominee / Kin:</td>
            <td><?= e($b['nominee_name'] ?: '—') ?> (<?= e($b['nominee_relation'] ?: '—') ?>)</td>
            <td class="label">Nominee CNIC:</td>
            <td><?= e($b['nominee_cnic'] ?: '—') ?></td>
        </tr>
    </table>

    <!-- Plot Details -->
    <div class="section-label">2. Allotted Property Specifications</div>
    <table class="table meta-table w-100 mb-3">
        <tr>
            <td class="label">Plot Number:</td>
            <td><strong class="fs-6 text-primary"><?= e($b['plot_number']) ?></strong></td>
            <td class="label">Block / Sector:</td>
            <td><strong><?= e($b['block_name']) ?></strong></td>
        </tr>
        <tr>
            <td class="label">Project Phase:</td>
            <td><?= e($b['phase_name']) ?></td>
            <td class="label">Category / Type:</td>
            <td><?= e($b['type_name']) ?></td>
        </tr>
        <tr>
            <td class="label">Plot Size:</td>
            <td><?= e($b['size_name']) ?> (<?= e($b['dimension_details'] ?? 'Standard') ?>)</td>
            <td class="label">Street / Location:</td>
            <td><?= e($b['street_road'] ?: 'Standard Road') ?></td>
        </tr>
    </table>

    <!-- Financial Schedule & Terms -->
    <div class="section-label">3. Financial Terms & Payment Plan</div>
    <table class="table meta-table w-100 mb-3">
        <tr>
            <td class="label">Base Plot Price:</td>
            <td><?= format_currency($b['base_price']) ?></td>
            <td class="label">Development Charges:</td>
            <td><?= format_currency($b['development_charges']) ?></td>
        </tr>
        <tr>
            <td class="label">Prime / Extra Charges:</td>
            <td><?= format_currency($b['extra_charges']) ?></td>
            <td class="label">Discount Allowed:</td>
            <td><?= format_currency($b['discount_amount']) ?></td>
        </tr>
        <tr class="bg-light">
            <td class="label text-primary">Total Net Sale Price:</td>
            <td><strong class="text-primary fs-6"><?= format_currency($b['net_sale_price']) ?></strong></td>
            <td class="label">Down Payment Due:</td>
            <td><strong><?= format_currency($b['down_payment_amount']) ?></strong></td>
        </tr>
        <tr>
            <td class="label">Payment Plan:</td>
            <td colspan="3"><?= count($schedules) - 1 ?> Regular Installments as per scheduled timetable.</td>
        </tr>
    </table>

    <!-- Terms & Conditions Summary -->
    <div class="section-label">4. Mandatory Terms & Governing Rules</div>
    <ol class="small text-muted ps-3 mb-4" style="line-height: 1.4; font-size: 0.78rem;">
        <li>All payments must be made strictly on or before due dates via cross cheque, pay order or official society online account.</li>
        <li>Physical possession of the plot will be handed over only upon complete clearance of all installments and development dues.</li>
        <li>Any default exceeding the grace period may incur policy surcharges or cancellation after required notice.</li>
        <li>This provisional allotment is subject to final layout approval and physical site demarcation by the engineering directorate.</li>
    </ol>

    <!-- Signatures -->
    <div class="row pt-4">
        <div class="col-4">
            <div class="sign-box">Applicant / Allottee Signature</div>
        </div>
        <div class="col-4">
            <div class="sign-box">Verified by Sales Manager</div>
        </div>
        <div class="col-4">
            <div class="sign-box">Authorized Secretary / Director</div>
        </div>
    </div>
</div>

</body>
</html>
