<?php
/**
 * HSMS - Journal Vouchers (JVs) Master Register & Manual JV Creation
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/accounting_service.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];

// Handle Manual JV Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $entryDate = trim($_POST['entry_date'] ?? date('Y-m-d'));
    $narration = trim($_POST['narration'] ?? '');
    $postAccounts = $_POST['account_id'] ?? [];
    $postDebits = $_POST['debit'] ?? [];
    $postCredits = $_POST['credit'] ?? [];
    $postMemos = $_POST['memo'] ?? [];

    $lines = [];
    foreach ($postAccounts as $i => $accId) {
        $aid = (int)$accId;
        $deb = (float)($postDebits[$i] ?? 0);
        $crd = (float)($postCredits[$i] ?? 0);
        $memo = trim((string)($postMemos[$i] ?? ''));

        if ($aid > 0 && ($deb > 0 || $crd > 0)) {
            $lines[] = [
                'account_id' => $aid,
                'debit'      => $deb,
                'credit'     => $crd,
                'memo'       => $memo
            ];
        }
    }

    if (empty($narration)) {
        $errors['narration'] = 'Journal entry narration is required.';
    }
    if (count($lines) < 2) {
        $errors['lines'] = 'A journal voucher must have at least two valid lines (Debit and Credit).';
    }

    if (empty($errors)) {
        try {
            $user = current_user();
            $jvId = AccountingService::postJournalEntry(
                $activeSocId,
                $entryDate,
                'journal',
                null,
                null,
                $narration,
                $lines,
                $user['id'] ?? 1
            );

            redirect_with_message(base_url('modules/accounts/journal_entries.php'), 'Journal Voucher posted successfully with balanced books!', 'success');
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

// Fetch all active accounts for dropdown
$stmtAccs = $db->query("
    SELECT id, account_code, account_name, account_type 
    FROM `accounts` 
    WHERE `status` = 'active' 
    ORDER BY `account_code` ASC
");
$accountsList = $stmtAccs->fetchAll();

// Search & Filter
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$sql = "
    SELECT je.*, u.name as created_by_name,
           (SELECT COUNT(*) FROM `journal_entry_lines` WHERE `journal_entry_id` = je.id) as lines_count
    FROM `journal_entries` je
    JOIN `users` u ON je.created_by = u.id
    WHERE je.society_id = :sid
";
$params = ['sid' => $activeSocId];

if ($search !== '') {
    $sql .= " AND (je.entry_number LIKE :s1 OR je.narration LIKE :s2)";
    $params['s1'] = "%{$search}%";
    $params['s2'] = "%{$search}%";
}
if ($typeFilter !== '') {
    $sql .= " AND je.entry_type = :etype";
    $params['etype'] = $typeFilter;
}
if ($startDate !== '') {
    $sql .= " AND je.entry_date >= :sdate";
    $params['sdate'] = $startDate;
}
if ($endDate !== '') {
    $sql .= " AND je.entry_date <= :edate";
    $params['edate'] = $endDate;
}

$sql .= " ORDER BY je.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$pageTitle = 'Journal Entries Register';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">General Journal & Vouchers</h4>
        <p class="text-muted small mb-0">Double-entry journal records for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/accounts/trial_balance.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-scale me-1"></i> Trial Balance
        </a>
        <a href="<?= base_url('modules/accounts/cash_bank.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Cash & Bank Book
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newJvModal">
            <i class="bi bi-plus-circle me-1"></i> New Journal Voucher
        </button>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
    </div>
<?php endif; ?>

<div class="card-hs">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by JV # or Narration..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Voucher Types</option>
                    <option value="journal" <?= ($typeFilter === 'journal') ? 'selected' : '' ?>>Journal (JV)</option>
                    <option value="receipt" <?= ($typeFilter === 'receipt') ? 'selected' : '' ?>>Receipt (CRV)</option>
                    <option value="payment" <?= ($typeFilter === 'payment') ? 'selected' : '' ?>>Payment (CPV)</option>
                    <option value="contra" <?= ($typeFilter === 'contra') ? 'selected' : '' ?>>Contra</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="Start Date">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= base_url('modules/accounts/journal_entries.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hs align-middle">
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Narration / Purpose</th>
                        <th>Lines</th>
                        <th class="text-end">Total Amount (Rs.)</th>
                        <th>Posted By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No journal entries found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($entries as $je): ?>
                            <tr>
                                <td class="fw-bold font-monospace text-primary"><?= e($je['entry_number']) ?></td>
                                <td><?= date('d M Y', strtotime($je['entry_date'])) ?></td>
                                <td>
                                    <?php
                                    $badge = match ($je['entry_type']) {
                                        'receipt' => 'bg-success',
                                        'payment' => 'bg-danger',
                                        'contra'  => 'bg-info text-dark',
                                        default   => 'bg-primary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= strtoupper($je['entry_type']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($je['narration']) ?></div>
                                    <?php if ($je['reference_type']): ?>
                                        <span class="text-muted small">Ref: <?= ucfirst($je['reference_type']) ?> #<?= (int)$je['reference_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= (int)$je['lines_count'] ?> lines</span></td>
                                <td class="text-end fw-bold fs-6">
                                    <?= format_currency($je['total_debit']) ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">Balanced (Dr = Cr)</div>
                                </td>
                                <td class="small text-muted"><?= e($je['created_by_name']) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewJvDetails(<?= (int)$je['id'] ?>, '<?= e($je['entry_number']) ?>')">
                                        <i class="bi bi-eye me-1"></i> View Lines
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: New Journal Voucher -->
<div class="modal fade" id="newJvModal" tabindex="-1" aria-labelledby="newJvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="jvForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newJvModalLabel"><i class="bi bi-journal-plus me-2 text-primary"></i> Post Manual Journal Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Voucher Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">General Narration / Purpose <span class="text-danger">*</span></label>
                            <input type="text" name="narration" class="form-control form-control-sm" placeholder="e.g. Month-end depreciation adjustment or bank charges" required>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm align-middle" id="linesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 38%;">Account Head</th>
                                    <th>Line Memo / Detail</th>
                                    <th style="width: 20%;">Debit (Rs.)</th>
                                    <th style="width: 20%;">Credit (Rs.)</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="linesTbody">
                                <!-- Row 1 -->
                                <tr>
                                    <td>
                                        <select name="account_id[]" class="form-select form-select-sm" required onchange="calculateTotals()">
                                            <option value="">Choose Account</option>
                                            <?php foreach ($accountsList as $acc): ?>
                                                <option value="<?= (int)$acc['id'] ?>"><?= e($acc['account_code']) ?> — <?= e($acc['account_name']) ?> (<?= ucfirst($acc['account_type']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="text" name="memo[]" class="form-control form-control-sm" placeholder="Memo"></td>
                                    <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control form-control-sm text-end line-debit" value="0.00" oninput="handleDebitInput(this)"></td>
                                    <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control form-control-sm text-end line-credit" value="0.00" oninput="handleCreditInput(this)"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                                <!-- Row 2 -->
                                <tr>
                                    <td>
                                        <select name="account_id[]" class="form-select form-select-sm" required onchange="calculateTotals()">
                                            <option value="">Choose Account</option>
                                            <?php foreach ($accountsList as $acc): ?>
                                                <option value="<?= (int)$acc['id'] ?>"><?= e($acc['account_code']) ?> — <?= e($acc['account_name']) ?> (<?= ucfirst($acc['account_type']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="text" name="memo[]" class="form-control form-control-sm" placeholder="Memo"></td>
                                    <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control form-control-sm text-end line-debit" value="0.00" oninput="handleDebitInput(this)"></td>
                                    <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control form-control-sm text-end line-credit" value="0.00" oninput="handleCreditInput(this)"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-end">Total Sum:</td>
                                    <td class="text-end" id="sumDebits">Rs. 0.00</td>
                                    <td class="text-end" id="sumCredits">Rs. 0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow()">
                                                <i class="bi bi-plus me-1"></i> Add Line
                                            </button>
                                            <div id="balanceAlert" class="badge bg-danger p-2 fs-6">
                                                Unbalanced: Difference Rs. 0.00
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitJvBtn" class="btn btn-sm btn-primary px-3 fw-semibold" disabled>Post Journal Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View JV Lines -->
<div class="modal fade" id="viewJvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="viewJvTitle">Journal Voucher Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="viewJvBody">
                <div class="text-center py-4 text-muted"><div class="spinner-border text-primary spinner-border-sm me-2"></div> Loading lines...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function handleDebitInput(input) {
    if (parseFloat(input.value) > 0) {
        const row = input.closest('tr');
        row.querySelector('.line-credit').value = '0.00';
    }
    calculateTotals();
}

function handleCreditInput(input) {
    if (parseFloat(input.value) > 0) {
        const row = input.closest('tr');
        row.querySelector('.line-debit').value = '0.00';
    }
    calculateTotals();
}

function calculateTotals() {
    let debits = 0.0;
    let credits = 0.0;

    document.querySelectorAll('.line-debit').forEach(d => {
        debits += parseFloat(d.value) || 0.0;
    });
    document.querySelectorAll('.line-credit').forEach(c => {
        credits += parseFloat(c.value) || 0.0;
    });

    document.getElementById('sumDebits').innerText = 'Rs. ' + debits.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('sumCredits').innerText = 'Rs. ' + credits.toLocaleString(undefined, {minimumFractionDigits: 2});

    const diff = Math.abs(debits - credits);
    const alertBox = document.getElementById('balanceAlert');
    const submitBtn = document.getElementById('submitJvBtn');

    if (diff < 0.01 && debits > 0) {
        alertBox.className = 'badge bg-success p-2 fs-6';
        alertBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> Strictly Balanced (Debit == Credit)';
        submitBtn.disabled = false;
    } else {
        alertBox.className = 'badge bg-danger p-2 fs-6';
        alertBox.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Unbalanced: Difference Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2});
        submitBtn.disabled = true;
    }
}

function addRow() {
    const tbody = document.getElementById('linesTbody');
    const firstRow = tbody.querySelector('tr');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('input').forEach(i => i.value = (i.name.includes('memo') ? '' : '0.00'));
    newRow.querySelector('select').selectedIndex = 0;
    tbody.appendChild(newRow);
    calculateTotals();
}

function removeRow(btn) {
    const tbody = document.getElementById('linesTbody');
    if (tbody.querySelectorAll('tr').length <= 2) {
        alert('A journal entry requires at least two lines.');
        return;
    }
    btn.closest('tr').remove();
    calculateTotals();
}

function viewJvDetails(jvId, jvNum) {
    document.getElementById('viewJvTitle').innerText = 'Journal Voucher Details: ' + jvNum;
    const body = document.getElementById('viewJvBody');
    body.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border text-primary spinner-border-sm me-2"></div> Loading lines...</div>';

    const modal = new bootstrap.Modal(document.getElementById('viewJvModal'));
    modal.show();

    fetch('<?= base_url('modules/accounts/get_jv_lines.php?id=') ?>' + jvId)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger m-3">Failed to load voucher lines.</div>';
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
