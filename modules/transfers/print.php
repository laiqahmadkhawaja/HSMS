<?php
/**
 * HSMS - Official Plot Transfer Deed & Certificate (Print View)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('transfers_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$transferId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT trf.*,
           seller.full_name as seller_name, seller.father_husband_name as seller_father,
           seller.cnic_passport as seller_cnic, seller.mobile as seller_mobile, seller.residential_address as seller_address,
           buyer.full_name as buyer_name, buyer.father_husband_name as buyer_father,
           buyer.cnic_passport as buyer_cnic, buyer.mobile as buyer_mobile, buyer.residential_address as buyer_address,
           p.plot_number, p.is_corner, p.is_boulevard, p.is_park_facing, ps.name as size_name, pt.name as type_name,
           b.block_name, ph.phase_name,
           bk.booking_number, bk.booking_date, bk.net_sale_price, bk.total_paid, bk.remaining_balance,
           u.name as approved_by_name
    FROM `plot_transfers` trf
    JOIN `plots` p ON trf.plot_id = p.id
    LEFT JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    LEFT JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON b.phase_id = ph.id
    JOIN `bookings` bk ON trf.booking_id = bk.id
    JOIN `customers` seller ON trf.from_customer_id = seller.id
    JOIN `customers` buyer ON trf.to_customer_id = buyer.id
    LEFT JOIN `users` u ON trf.approved_by = u.id
    WHERE trf.id = :id AND trf.society_id = :sid
");
$stmt->execute(['id' => $transferId, 'sid' => $activeSocId]);
$trf = $stmt->fetch();

if (!$trf) {
    die("Plot Transfer Deed not found or unauthorized access.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Deed — <?= e($trf['transfer_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
        }
        .deed-container {
            max-width: 820px;
            margin: 25px auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 2px solid #0f172a;
            position: relative;
        }
        .deed-border {
            border: 1px dashed #64748b;
            padding: 25px;
        }
        .deed-header {
            text-align: center;
            border-bottom: 2px double #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .deed-title {
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 10px;
            color: #0f172a;
        }
        .section-heading {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f1f5f9;
            padding: 4px 10px;
            border-left: 3px solid #0f172a;
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
            background: #fafafa;
            border: 1px solid #e2e8f0;
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
            .deed-container { box-shadow: none; border: 2px solid #000; margin: 0; width: 100%; max-width: 100%; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="text-center my-3 no-print">
    <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-semibold">
        <i class="bi bi-printer me-1"></i> Print Deed / Certificate
    </button>
    <a href="<?= base_url('modules/transfers/index.php') ?>" class="btn btn-outline-secondary px-4 py-2 ms-2">
        Back to Register
    </a>
</div>

<div class="deed-container">
    <div class="deed-border">
        <!-- Header -->
        <div class="deed-header">
            <h3 class="fw-bold mb-0 text-uppercase"><?= e($society['name'] ?? 'Housing Society') ?></h3>
            <p class="text-muted small mb-1"><?= e($society['address'] ?? 'Head Office') ?> | Tel: <?= e($society['contact_number'] ?? 'N/A') ?></p>
            <div class="badge bg-dark text-white px-3 py-1">REG # <?= e($society['registration_number'] ?: 'HSMS-REG-001') ?></div>
            <div class="deed-title">Deed of Allotment Transfer & Assignment</div>
            <div class="d-flex justify-content-between mt-3 small fw-semibold">
                <span>TRANSFER DEED #: <strong class="text-primary"><?= e($trf['transfer_number']) ?></strong></span>
                <span>EXECUTION DATE: <strong><?= date('d F Y', strtotime($trf['transfer_date'])) ?></strong></span>
            </div>
        </div>

        <!-- 1. Property Details -->
        <div class="section-heading">1. Property / Plot Particulars</div>
        <div class="row g-2 mb-3">
            <div class="col-3">
                <div class="field-label">Plot Number</div>
                <div class="field-val fs-6 text-primary"><?= e($trf['plot_number']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Sector / Block</div>
                <div class="field-val"><?= e($trf['block_name']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Phase</div>
                <div class="field-val"><?= e($trf['phase_name']) ?></div>
            </div>
            <div class="col-3">
                <div class="field-label">Category / Size</div>
                <div class="field-val"><?= e($trf['size_name'] ?: '5 Marla') ?> (<?= e($trf['type_name'] ?: 'Residential') ?>)</div>
            </div>
        </div>

        <!-- 2. Transferor (Seller) Details -->
        <div class="section-heading">2. Transferor Particulars (Previous Allottee)</div>
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="field-label">Full Name</div>
                <div class="field-val"><?= e($trf['seller_name']) ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">S/O, D/O, W/O</div>
                <div class="field-val"><?= e($trf['seller_father'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">CNIC / Passport #</div>
                <div class="field-val"><?= e($trf['seller_cnic']) ?></div>
            </div>
            <div class="col-8">
                <div class="field-label">Residential Address</div>
                <div class="field-val"><?= e($trf['seller_address'] ?: 'On Record') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">Mobile Contact</div>
                <div class="field-val"><?= e($trf['seller_mobile']) ?></div>
            </div>
        </div>

        <!-- 3. Transferee (Buyer) Details -->
        <div class="section-heading">3. Transferee Particulars (New Allottee)</div>
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="field-label">Full Name</div>
                <div class="field-val text-success"><?= e($trf['buyer_name']) ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">S/O, D/O, W/O</div>
                <div class="field-val"><?= e($trf['buyer_father'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">CNIC / Passport #</div>
                <div class="field-val"><?= e($trf['buyer_cnic']) ?></div>
            </div>
            <div class="col-8">
                <div class="field-label">Residential Address</div>
                <div class="field-val"><?= e($trf['buyer_address'] ?: 'On Record') ?></div>
            </div>
            <div class="col-4">
                <div class="field-label">Mobile Contact</div>
                <div class="field-val"><?= e($trf['buyer_mobile']) ?></div>
            </div>
        </div>

        <!-- 4. Financial Reconciliation -->
        <div class="section-heading">4. Financial Consideration & Balance Takeover</div>
        <table class="table table-bordered table-sm mb-3">
            <thead class="table-light">
                <tr>
                    <th>Original Booking #</th>
                    <th>Net Contract Price</th>
                    <th>Total Cleared by Transferor</th>
                    <th>Outstanding Balance Assumed</th>
                    <th>Transfer Fee Status</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fw-semibold">
                    <td><?= e($trf['booking_number']) ?></td>
                    <td><?= format_currency($trf['net_sale_price']) ?></td>
                    <td class="text-success"><?= format_currency($trf['total_paid']) ?></td>
                    <td class="text-danger"><?= format_currency($trf['pending_balance_transferred']) ?></td>
                    <td>
                        <?= format_currency($trf['transfer_fee']) ?> 
                        <span class="badge bg-success"><?= ucfirst($trf['transfer_fee_status']) ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Legal Declaration -->
        <div class="legal-clause">
            <strong>MUTUAL DECLARATION & AFFIDAVIT:</strong><br>
            The Transferor hereby solemnly affirms that he/she has voluntarily transferred and relinquished all rights, title, interests, and claims in the aforementioned plot in favor of the Transferee. The Transferee hereby accepts the transfer of allotment subject to the society bylaws, rules, and regulations, and assumes absolute legal liability for the outstanding balance of <strong>Rs. <?= number_format((float)$trf['pending_balance_transferred'], 2) ?></strong> payable as per the approved installment schedule.
        </div>

        <!-- Signatures -->
        <div class="row pt-4">
            <div class="col-4 sig-box">
                <div class="sig-line">TRANSFEROR (SELLER)<br><span class="text-muted" style="font-size: 10px;">Signature & Thumb Impression</span></div>
            </div>
            <div class="col-4 sig-box">
                <div class="sig-line">TRANSFEREE (BUYER)<br><span class="text-muted" style="font-size: 10px;">Signature & Thumb Impression</span></div>
            </div>
            <div class="col-4 sig-box">
                <div class="sig-line">AUTHORIZED OFFICER<br><span class="text-muted" style="font-size: 10px;"><?= e($society['name'] ?? 'Society') ?> Seal</span></div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
