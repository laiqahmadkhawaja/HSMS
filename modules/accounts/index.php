<?php
/**
 * HSMS - Chart of Accounts (COA) Master
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];

// Handle Add New Account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $accountCode = trim($_POST['account_code'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $accountType = trim($_POST['account_type'] ?? '');
    $subType = trim($_POST['sub_type'] ?? '');
    $openingBal = (float)($_POST['opening_balance'] ?? 0);

    $validator = new Validator($_POST);
    $validator->required('account_code', 'Account Code')
              ->required('account_name', 'Account Name')
              ->required('account_type', 'Account Type');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Check uniqueness of account code
        $stmtCheck = $db->prepare("SELECT id FROM `accounts` WHERE `account_code` = :code LIMIT 1");
        $stmtCheck->execute(['code' => $accountCode]);
        if ($stmtCheck->fetchColumn()) {
            $errors['account_code'] = "Account Code '{$accountCode}' is already in use.";
        } else {
            $stmtIns = $db->prepare("
                INSERT INTO `accounts` (
                    `society_id`, `account_code`, `account_name`, `account_type`,
                    `sub_type`, `opening_balance`, `current_balance`, `is_system`, `status`
                ) VALUES (
                    :sid, :code, :name, :atype,
                    :stype, :open_bal, :curr_bal, 0, 'active'
                )
            ");
            $stmtIns->execute([
                'sid'      => $activeSocId,
                'code'     => $accountCode,
                'name'     => $accountName,
                'atype'    => $accountType,
                'stype'    => $subType ?: null,
                'open_bal' => $openingBal,
                'curr_bal' => $openingBal
            ]);
            $newAccId = (int)$db->lastInsertId();
            log_audit('ACCOUNT_CREATED', 'accounts', $newAccId, null, [
                'code' => $accountCode,
                'name' => $accountName,
                'type' => $accountType
            ], $activeSocId);

            redirect_with_message(base_url('modules/accounts/index.php'), 'New account added to Chart of Accounts!', 'success');
        }
    }
}

// Fetch all accounts grouped by account_type
$stmtAcc = $db->query("
    SELECT * 
    FROM `accounts` 
    ORDER BY `account_code` ASC
");
$allAccounts = $stmtAcc->fetchAll();

$groupedAccounts = [
    'asset'     => [],
    'liability' => [],
    'equity'    => [],
    'income'    => [],
    'expense'   => []
];

foreach ($allAccounts as $acc) {
    $t = strtolower($acc['account_type']);
    if (isset($groupedAccounts[$t])) {
        $groupedAccounts[$t][] = $acc;
    }
}

$pageTitle = 'Chart of Accounts';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Chart of Accounts (COA)</h4>
        <p class="text-muted small mb-0">General ledger accounts hierarchy & current balances for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/accounts/trial_balance.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-scale me-1"></i> Trial Balance
        </a>
        <a href="<?= base_url('modules/accounts/cash_bank.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Cash & Bank Book
        </a>
        <a href="<?= base_url('modules/accounts/journal_entries.php') ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-journal-text me-1"></i> Journal Vouchers
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="bi bi-plus-circle me-1"></i> Add Account
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

<!-- 5 Accounting Pillars Tabs -->
<ul class="nav nav-pills mb-3 bg-light p-1 rounded border" id="coaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active py-1 px-3 fw-semibold" id="tab-asset" data-bs-toggle="pill" data-bs-target="#content-asset" type="button" role="tab">
            1000 — Assets (<?= count($groupedAccounts['asset']) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-1 px-3 fw-semibold" id="tab-liability" data-bs-toggle="pill" data-bs-target="#content-liability" type="button" role="tab">
            2000 — Liabilities (<?= count($groupedAccounts['liability']) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-1 px-3 fw-semibold" id="tab-equity" data-bs-toggle="pill" data-bs-target="#content-equity" type="button" role="tab">
            3000 — Equity (<?= count($groupedAccounts['equity']) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-1 px-3 fw-semibold" id="tab-income" data-bs-toggle="pill" data-bs-target="#content-income" type="button" role="tab">
            4000 — Revenue / Income (<?= count($groupedAccounts['income']) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link py-1 px-3 fw-semibold" id="tab-expense" data-bs-toggle="pill" data-bs-target="#content-expense" type="button" role="tab">
            5000 — Expenses (<?= count($groupedAccounts['expense']) ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="coaTabContent">
    <?php
    $categoriesMeta = [
        'asset'     => ['title' => 'Assets (Resources & Receivables)', 'code_prefix' => '1', 'badge' => 'bg-primary'],
        'liability' => ['title' => 'Liabilities (Obligations & Payables)', 'code_prefix' => '2', 'badge' => 'bg-danger'],
        'equity'    => ['title' => 'Equity (Capital & Retained Surplus)', 'code_prefix' => '3', 'badge' => 'bg-dark'],
        'income'    => ['title' => 'Revenue & Operational Income', 'code_prefix' => '4', 'badge' => 'bg-success'],
        'expense'   => ['title' => 'Operating & Development Expenses', 'code_prefix' => '5', 'badge' => 'bg-warning text-dark']
    ];

    foreach ($categoriesMeta as $catKey => $meta):
        $activeClass = ($catKey === 'asset') ? 'show active' : '';
    ?>
    <div class="tab-pane fade <?= $activeClass ?>" id="content-<?= $catKey ?>" role="tabpanel">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><span class="badge <?= $meta['badge'] ?> me-2"><?= ucfirst($catKey) ?></span> <?= e($meta['title']) ?></span>
                <span class="text-muted small">Standard Normal Balance: <strong><?= in_array($catKey, ['asset', 'expense']) ? 'DEBIT' : 'CREDIT' ?></strong></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hs align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Code</th>
                                <th>Account Title</th>
                                <th>Sub Type / Classification</th>
                                <th>Opening Balance</th>
                                <th class="text-end">Current Balance</th>
                                <th>Classification</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groupedAccounts[$catKey])): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No accounts defined under this head.</td></tr>
                            <?php else: ?>
                                <?php foreach ($groupedAccounts[$catKey] as $acc): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary"><?= e($acc['account_code']) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($acc['account_name']) ?></div>
                                            <?php if ($acc['is_system']): ?>
                                                <span class="badge bg-light text-muted border" style="font-size: 0.65rem;">System Account</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e(ucwords(str_replace('_', ' ', $acc['sub_type'] ?: 'general'))) ?></span>
                                        </td>
                                        <td class="text-muted small"><?= format_currency($acc['opening_balance']) ?></td>
                                        <td class="text-end fw-bold fs-6">
                                            <?= format_currency($acc['current_balance']) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $meta['badge'] ?>"><?= ucfirst($acc['account_type']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add New Account -->
<div class="modal fade" id="newAccountModal" tabindex="-1" aria-labelledby="newAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newAccountModalLabel">Add General Ledger Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Head of Account <span class="text-danger">*</span></label>
                        <select name="account_type" id="modal_account_type" class="form-select form-select-sm" required onchange="handleTypeChange(this.value)">
                            <option value="">Select Account Category</option>
                            <option value="asset">1000 — Asset</option>
                            <option value="liability">2000 — Liability</option>
                            <option value="equity">3000 — Equity</option>
                            <option value="income">4000 — Income / Revenue</option>
                            <option value="expense">5000 — Expense</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="account_code" id="modal_account_code" class="form-control form-control-sm font-monospace" placeholder="e.g. 5010" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Classification / Sub-Type</label>
                            <input type="text" name="sub_type" class="form-control form-control-sm" placeholder="e.g. bank, utilities, marketing">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Account Name / Title <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="form-control form-control-sm" placeholder="e.g. Site Security Guard Salaries" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Opening Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control form-control-sm" value="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleTypeChange(val) {
    const codeInput = document.getElementById('modal_account_code');
    const prefixMap = {
        'asset': '1',
        'liability': '2',
        'equity': '3',
        'income': '4',
        'expense': '5'
    };
    if (prefixMap[val] && !codeInput.value) {
        codeInput.placeholder = 'e.g. ' + prefixMap[val] + '020';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
