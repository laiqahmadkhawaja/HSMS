<?php
/**
 * HSMS - Cash & Bank Book Statement
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

// Fetch all Cash & Bank accounts
$stmtAccounts = $db->query("
    SELECT id, account_code, account_name, sub_type, opening_balance, current_balance
    FROM `accounts`
    WHERE `account_type` = 'asset' AND `sub_type` IN ('cash', 'bank') AND `status` = 'active'
    ORDER BY `sub_type` ASC, `account_name` ASC
");
$accounts = $stmtAccounts->fetchAll();

$selectedAccId = (int)($_GET['account_id'] ?? ($accounts[0]['id'] ?? 0));
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

$selectedAccount = null;
foreach ($accounts as $a) {
    if ((int)$a['id'] === $selectedAccId) {
        $selectedAccount = $a;
        break;
    }
}

$openingBalance = 0.0;
$transactions = [];
$totalInflow = 0.0;
$totalOutflow = 0.0;
$closingBalance = 0.0;

if ($selectedAccount) {
    // 1. Calculate opening balance as of startDate
    $stmtOpen = $db->prepare("
        SELECT COALESCE(SUM(jel.debit - jel.credit), 0)
        FROM `journal_entry_lines` jel
        JOIN `journal_entries` je ON jel.journal_entry_id = je.id
        WHERE jel.account_id = :aid AND je.society_id = :sid AND je.entry_date < :sdate
    ");
    $stmtOpen->execute([
        'aid'   => $selectedAccId,
        'sid'   => $activeSocId,
        'sdate' => $startDate
    ]);
    $periodPriorDelta = (float)$stmtOpen->fetchColumn();
    $openingBalance = round((float)$selectedAccount['opening_balance'] + $periodPriorDelta, 2);

    // 2. Fetch transactions within period
    $stmtTx = $db->prepare("
        SELECT je.entry_date, je.entry_number, je.entry_type, je.narration,
               jel.debit, jel.credit, jel.memo
        FROM `journal_entry_lines` jel
        JOIN `journal_entries` je ON jel.journal_entry_id = je.id
        WHERE jel.account_id = :aid AND je.society_id = :sid
          AND je.entry_date >= :sdate AND je.entry_date <= :edate
        ORDER BY je.entry_date ASC, je.id ASC
    ");
    $stmtTx->execute([
        'aid'   => $selectedAccId,
        'sid'   => $activeSocId,
        'sdate' => $startDate,
        'edate' => $endDate
    ]);
    $rawTx = $stmtTx->fetchAll();

    $running = $openingBalance;
    foreach ($rawTx as $row) {
        $deb = (float)$row['debit'];
        $crd = (float)$row['credit'];
        $running = round($running + ($deb - $crd), 2);

        $totalInflow += $deb;
        $totalOutflow += $crd;

        $transactions[] = [
            'date'            => $row['entry_date'],
            'voucher_number'  => $row['entry_number'],
            'type'            => $row['entry_type'],
            'narration'       => $row['narration'],
            'memo'            => $row['memo'],
            'debit'           => $deb,
            'credit'          => $crd,
            'running_balance' => $running
        ];
    }
    $closingBalance = $running;
}

$pageTitle = 'Cash & Bank Book';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Cash & Bank Book Statement</h4>
        <p class="text-muted small mb-0">Daily cash and bank account transactions for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/accounts/journal_entries.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i> Journal Vouchers
        </a>
        <a href="<?= base_url('modules/accounts/trial_balance.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-scale me-1"></i> Trial Balance
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print Statement
        </button>
    </div>
</div>

<div class="card-hs mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Select Cash / Bank Account</label>
                <select name="account_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>" <?= ($selectedAccId === (int)$acc['id']) ? 'selected' : '' ?>>
                            <?= e($acc['account_code']) ?> — <?= e($acc['account_name']) ?> (<?= ucfirst($acc['sub_type']) ?>) - Current: Rs. <?= number_format((float)$acc['current_balance'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-filter me-1"></i> View Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Opening Balance (<?= date('d M Y', strtotime($startDate)) ?>)</div>
            <div class="fs-4 fw-bold text-dark mt-1"><?= format_currency($openingBalance) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Receipts (Debits / Inflows)</div>
            <div class="fs-4 fw-bold text-success mt-1"><?= format_currency($totalInflow) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Total Payments (Credits / Outflows)</div>
            <div class="fs-4 fw-bold text-danger mt-1"><?= format_currency($totalOutflow) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-hs p-3">
            <div class="text-muted small fw-semibold">Closing Balance (<?= date('d M Y', strtotime($endDate)) ?>)</div>
            <div class="fs-4 fw-bold text-primary mt-1"><?= format_currency($closingBalance) ?></div>
        </div>
    </div>
</div>

<div class="card-hs">
    <div class="card-header bg-white py-2">
        <span class="fw-bold"><?= e($selectedAccount['account_name'] ?? 'Account') ?> Ledger Statement</span>
        <span class="text-muted small ms-2">(Period: <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 110px;">Date</th>
                        <th style="width: 130px;">Voucher #</th>
                        <th>Type</th>
                        <th>Particulars / Narration</th>
                        <th class="text-end" style="width: 140px;">Money In (Dr)</th>
                        <th class="text-end" style="width: 140px;">Money Out (Cr)</th>
                        <th class="text-end" style="width: 150px;">Balance (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light fw-bold">
                        <td colspan="6">OPENING BALANCE B/F (As of <?= date('d M Y', strtotime($startDate)) ?>)</td>
                        <td class="text-end"><?= format_currency($openingBalance) ?></td>
                    </tr>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No cash/bank transactions during this date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($tx['date'])) ?></td>
                                <td class="font-monospace text-primary fw-semibold"><?= e($tx['voucher_number']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= strtoupper($tx['type']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($tx['narration']) ?></div>
                                    <?php if (!empty($tx['memo']) && $tx['memo'] !== $tx['narration']): ?>
                                        <div class="text-muted small"><?= e($tx['memo']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    <?= ($tx['debit'] > 0) ? format_currency($tx['debit']) : '-' ?>
                                </td>
                                <td class="text-end text-danger fw-semibold">
                                    <?= ($tx['credit'] > 0) ? format_currency($tx['credit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= format_currency($tx['running_balance']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Total Period Activity:</td>
                        <td class="text-end text-success"><?= format_currency($totalInflow) ?></td>
                        <td class="text-end text-danger"><?= format_currency($totalOutflow) ?></td>
                        <td class="text-end text-primary fs-6"><?= format_currency($closingBalance) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
