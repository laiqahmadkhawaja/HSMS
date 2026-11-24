<?php
/**
 * HSMS - Record Expense Voucher & Disbursement
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/accounting_service.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $categoryId = (int)($_POST['expense_category_id'] ?? 0);
    $paymentAccountId = (int)($_POST['payment_account_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paidTo = trim($_POST['paid_to'] ?? '');
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $expenseDate = trim($_POST['expense_date'] ?? date('Y-m-d'));

    $validator = new Validator($_POST);
    $validator->required('expense_category_id', 'Expense Category')
              ->required('payment_account_id', 'Disbursing Account')
              ->minNumber('amount', 1.0, 'Expense Amount')
              ->required('paid_to', 'Payee Name')
              ->required('expense_date', 'Expense Date')
              ->date('expense_date', 'Expense Date');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Handle optional attachment upload
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['attachment']['tmp_name']);

            if (in_array($mime, $allowedMimes, true) && $_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                $ext = match($mime) {
                    'image/jpeg'      => 'jpg',
                    'image/png'       => 'png',
                    'application/pdf' => 'pdf',
                    default           => 'dat'
                };
                $fileName = 'exp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetDir = UPLOADS_PATH . '/attachments';
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetDir . '/' . $fileName)) {
                    $attachmentPath = 'uploads/attachments/' . $fileName;
                }
            } else {
                $errors['attachment'] = 'Attachment must be a valid PDF, JPEG or PNG file under 5 MB.';
            }
        }

        if (empty($errors)) {
            try {
                $user = current_user();
                $expenseId = AccountingService::postExpenseVoucher(
                    $activeSocId,
                    $categoryId,
                    $paymentAccountId,
                    $amount,
                    $paidTo,
                    $referenceNo,
                    $description,
                    $attachmentPath,
                    $expenseDate,
                    $user['id'] ?? 1
                );

                redirect_with_message(base_url('modules/expenses/index.php'), 'Expense voucher posted successfully with balanced General Ledger entries!', 'success');
            } catch (Throwable $e) {
                $errors['general'] = $e->getMessage();
            }
        }
    }
}

// Fetch active categories
$stmtCats = $db->query("SELECT id, name FROM `expense_categories` WHERE `status` = 'active' ORDER BY `name` ASC");
$categories = $stmtCats->fetchAll();

// Fetch active cash/bank accounts
$stmtAccounts = $db->query("
    SELECT id, account_code, account_name, sub_type, current_balance 
    FROM `accounts` 
    WHERE `account_type` = 'asset' AND `sub_type` IN ('cash', 'bank') AND `status` = 'active'
    ORDER BY `sub_type` ASC, `account_name` ASC
");
$accounts = $stmtAccounts->fetchAll();

$pageTitle = 'Record Expense';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-wallet-fill me-2 text-danger"></i> Record Expense Voucher & Cash Disbursement</span>
                <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Register</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expense Category <span class="text-danger">*</span></label>
                            <select name="expense_category_id" class="form-select form-select-sm <?= isset($errors['expense_category_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= ((int)($_POST['expense_category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['expense_category_id'])): ?><div class="invalid-feedback"><?= e($errors['expense_category_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Disbursing Account (Cash / Bank) <span class="text-danger">*</span></label>
                            <select name="payment_account_id" class="form-select form-select-sm <?= isset($errors['payment_account_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose Account</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= (int)$acc['id'] ?>" <?= ((int)($_POST['payment_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>>
                                        <?= e($acc['account_code']) ?> — <?= e($acc['account_name']) ?> (<?= ucfirst($acc['sub_type']) ?>) - Available: Rs. <?= number_format((float)$acc['current_balance'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['payment_account_id'])): ?><div class="invalid-feedback"><?= e($errors['payment_account_id']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Paid To (Payee Name / Vendor) <span class="text-danger">*</span></label>
                            <input type="text" name="paid_to" class="form-control form-control-sm <?= isset($errors['paid_to']) ? 'is-invalid' : '' ?>" placeholder="e.g. Al-Madina Hardware / Ali Raza" value="<?= e($_POST['paid_to'] ?? '') ?>" required>
                            <?php if (isset($errors['paid_to'])): ?><div class="invalid-feedback"><?= e($errors['paid_to']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Disbursed Amount (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-sm <?= isset($errors['amount']) ? 'is-invalid' : '' ?>" placeholder="e.g. 75000.00" value="<?= e($_POST['amount'] ?? '') ?>" required>
                            <?php if (isset($errors['amount'])): ?><div class="invalid-feedback"><?= e($errors['amount']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Expense Date <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control form-control-sm <?= isset($errors['expense_date']) ? 'is-invalid' : '' ?>" value="<?= e($_POST['expense_date'] ?? date('Y-m-d')) ?>" required>
                            <?php if (isset($errors['expense_date'])): ?><div class="invalid-feedback"><?= e($errors['expense_date']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Invoice # / Cheque # / Ref</label>
                            <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="e.g. CHQ-981245 / INV-2026-88" value="<?= e($_POST['reference_no'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Purpose</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Briefly state the business justification or item details"><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Attach Bill / Receipt / Voucher Scan</label>
                        <input type="file" name="attachment" class="form-control form-control-sm <?= isset($errors['attachment']) ? 'is-invalid' : '' ?>" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if (isset($errors['attachment'])): ?><div class="invalid-feedback"><?= e($errors['attachment']) ?></div><?php endif; ?>
                        <div class="form-text small">Accepted formats: PDF, JPG, PNG (Max 5 MB).</div>
                    </div>

                    <div class="text-end">
                        <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-sm btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger btn-sm px-4 py-2 fw-semibold">
                            <i class="bi bi-check-circle me-1"></i> Post Expense Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
