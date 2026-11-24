<?php
/**
 * HSMS - Society Trial Balance Statement
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/accounting_service.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$asOfDate = trim($_GET['as_of'] ?? date('Y-m-d'));
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

$tb = AccountingService::getTrialBalance($activeSocId, $asOfDate);

// Handle CSV Export
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Trial_Balance_' . date('Ymd', strtotime($asOfDate)) . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trial Balance Statement', $society['name'] ?? 'Society', 'As Of:', $asOfDate]);
    fputcsv($output, ['Account Code', 'Account Title', 'Type', 'Debit (Rs.)', 'Credit (Rs.)']);

    foreach ($tb['accounts'] as $row) {
        fputcsv($output, [
            $row['account_code'],
            $row['account_name'],
            ucfirst($row['account_type']),
            number_format($row['debit'], 2, '.', ''),
            number_format($row['credit'], 2, '.', '')
        ]);
    }
    fputcsv($output, ['TOTAL', '', '', number_format($tb['total_debit'], 2, '.', ''), number_format($tb['total_credit'], 2, '.', '')]);
    fclose($output);
    exit;
}

$pageTitle = 'Trial Balance Statement';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Trial Balance Statement</h4>
        <p class="text-muted small mb-0">General ledger trial balance for <strong><?= e($society['name'] ?? 'Society') ?></strong> as of <strong><?= date('d F Y', strtotime($asOfDate)) ?></strong></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= base_url('modules/accounts/trial_balance.php?as_of=' . urlencode($asOfDate) . '&export=csv') ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="card-hs mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-0">Statement As Of Date</label>
            </div>
            <div class="col-md-4">
                <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($asOfDate) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Apply Date</button>
            </div>
        </form>
    </div>
</div>

<!-- Equilibrium Seal -->
<?php if ($tb['is_balanced']): ?>
    <div class="alert alert-success py-2 d-flex align-items-center mb-4">
        <i class="bi bi-shield-check fs-4 me-2"></i>
        <div>
            <strong class="d-block">TRIAL BALANCE IS IN STRICT EQUILIBRIUM</strong>
            <span class="small">Total Debits (Rs. <?= number_format($tb['total_debit'], 2) ?>) exactly match Total Credits (Rs. <?= number_format($tb['total_credit'], 2) ?>). Double-entry accounting integrity verified.</span>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-danger py-2 d-flex align-items-center mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
        <div>
            <strong class="d-block">WARNING: TRIAL BALANCE OUT OF BALANCE</strong>
            <span class="small">Discrepancy detected! Debits: Rs. <?= number_format($tb['total_debit'], 2) ?> vs Credits: Rs. <?= number_format($tb['total_credit'], 2) ?> (Difference: Rs. <?= number_format($tb['difference'], 2) ?>).</span>
        </div>
    </div>
<?php endif; ?>

<div class="card-hs">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 140px;">Account Code</th>
                        <th>Account Title</th>
                        <th>Classification</th>
                        <th class="text-end" style="width: 200px;">Debit (Rs.)</th>
                        <th class="text-end" style="width: 200px;">Credit (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tb['accounts'])): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No accounts with non-zero balances as of this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tb['accounts'] as $acc): ?>
                            <tr>
                                <td class="font-monospace text-primary fw-bold"><?= e($acc['account_code']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($acc['account_name']) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $bClass = match ($acc['account_type']) {
                                        'asset'     => 'bg-primary',
                                        'liability' => 'bg-danger',
                                        'equity'    => 'bg-dark',
                                        'income'    => 'bg-success',
                                        default     => 'bg-warning text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= ucfirst($acc['account_type']) ?></span>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= ($acc['debit'] > 0) ? format_currency($acc['debit']) : '-' ?>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?= ($acc['credit'] > 0) ? format_currency($acc['credit']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold fs-6">
                    <tr>
                        <td colspan="3" class="text-end text-uppercase">Grand Total:</td>
                        <td class="text-end text-primary"><?= format_currency($tb['total_debit']) ?></td>
                        <td class="text-end text-success"><?= format_currency($tb['total_credit']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
