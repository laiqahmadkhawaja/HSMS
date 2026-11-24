<?php
/**
 * HSMS - Create Installment Plan Template
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('masters_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name = trim($_POST['plan_name'] ?? '');
    $code = strtoupper(trim($_POST['plan_code'] ?? ''));
    $duration = (int)($_POST['duration_months'] ?? 12);
    $dpType = in_array($_POST['down_payment_type'] ?? '', ['percentage', 'fixed']) ? $_POST['down_payment_type'] : 'percentage';
    $dpValue = (float)($_POST['down_payment_value'] ?? 0);
    $frequency = in_array($_POST['frequency'] ?? '', ['monthly', 'quarterly', 'semi_annual', 'annual', 'custom']) ? $_POST['frequency'] : 'monthly';
    $totalInstallments = (int)($_POST['total_installments'] ?? 1);
    $graceDays = (int)($_POST['grace_period_days'] ?? 10);
    $lateFeeType = in_array($_POST['late_fee_type'] ?? '', ['none', 'fixed', 'percentage']) ? $_POST['late_fee_type'] : 'none';
    $lateFeeValue = (float)($_POST['late_fee_value'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $desc = trim($_POST['description'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('plan_name', 'Plan Name')
              ->required('plan_code', 'Plan Code')
              ->minNumber('duration_months', 1, 'Duration')
              ->minNumber('total_installments', 1, 'Total Installments')
              ->minNumber('down_payment_value', 0, 'Down Payment Value');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        $stmt = $db->prepare("
            INSERT INTO `installment_plan_templates` (
                `society_id`, `plan_name`, `plan_code`, `duration_months`, `down_payment_type`,
                `down_payment_value`, `frequency`, `total_installments`, `grace_period_days`,
                `late_fee_type`, `late_fee_value`, `status`, `description`
            ) VALUES (
                :sid, :name, :code, :dur, :dpt,
                :dpv, :freq, :ti, :grace,
                :lft, :lfv, :status, :desc
            )
        ");
        $stmt->execute([
            'sid'    => $activeSocId,
            'name'   => $name,
            'code'   => $code,
            'dur'    => $duration,
            'dpt'    => $dpType,
            'dpv'    => $dpValue,
            'freq'   => $frequency,
            'ti'     => $totalInstallments,
            'grace'  => $graceDays,
            'lft'    => $lateFeeType,
            'lfv'    => $lateFeeValue,
            'status' => $status,
            'desc'   => $desc ?: null
        ]);

        $newId = (int)$db->lastInsertId();
        log_audit('PLAN_TEMPLATE_CREATED', 'installment_plans', $newId, null, ['name' => $name, 'code' => $code], $activeSocId);
        redirect_with_message(base_url('modules/installment_plans/index.php'), "Plan Template '{$name}' created successfully!", 'success');
    }
}

$pageTitle = 'Add Installment Plan Template';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-calendar-check me-2"></i> Create Payment Plan Template</span>
                <a href="<?= base_url('modules/installment_plans/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Templates</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" name="plan_name" class="form-control form-control-sm <?= isset($errors['plan_name']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. 3-Year Flexible Quarterly" required value="<?= e($_POST['plan_name'] ?? '') ?>">
                            <?php if (isset($errors['plan_name'])): ?><div class="invalid-feedback"><?= e($errors['plan_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plan Code <span class="text-danger">*</span></label>
                            <input type="text" name="plan_code" class="form-control form-control-sm <?= isset($errors['plan_code']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. QTR-36M" required value="<?= e($_POST['plan_code'] ?? '') ?>">
                            <?php if (isset($errors['plan_code'])): ?><div class="invalid-feedback"><?= e($errors['plan_code']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Total Duration (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_months" class="form-control form-control-sm" required value="<?= e($_POST['duration_months'] ?? '36') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Installment Frequency <span class="text-danger">*</span></label>
                            <select name="frequency" class="form-select form-select-sm">
                                <option value="monthly" <?= (($_POST['frequency'] ?? 'monthly') === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                                <option value="quarterly" <?= (($_POST['frequency'] ?? '') === 'quarterly') ? 'selected' : '' ?>>Quarterly</option>
                                <option value="semi_annual" <?= (($_POST['frequency'] ?? '') === 'semi_annual') ? 'selected' : '' ?>>Semi-Annual (Every 6 Months)</option>
                                <option value="annual" <?= (($_POST['frequency'] ?? '') === 'annual') ? 'selected' : '' ?>>Annual (Yearly)</option>
                                <option value="custom" <?= (($_POST['frequency'] ?? '') === 'custom') ? 'selected' : '' ?>>Custom Schedule</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Down Payment Type</label>
                            <select name="down_payment_type" class="form-select form-select-sm">
                                <option value="percentage" <?= (($_POST['down_payment_type'] ?? 'percentage') === 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                                <option value="fixed" <?= (($_POST['down_payment_type'] ?? '') === 'fixed') ? 'selected' : '' ?>>Fixed Amount (PKR)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Down Payment Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="down_payment_value" class="form-control form-control-sm" required value="<?= e($_POST['down_payment_value'] ?? '20.00') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Installments Count <span class="text-danger">*</span></label>
                            <input type="number" name="total_installments" class="form-control form-control-sm" required value="<?= e($_POST['total_installments'] ?? '36') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Grace Period (Days)</label>
                            <input type="number" name="grace_period_days" class="form-control form-control-sm" value="<?= e($_POST['grace_period_days'] ?? '10') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Fee Policy</label>
                            <select name="late_fee_type" class="form-select form-select-sm">
                                <option value="none" <?= (($_POST['late_fee_type'] ?? 'none') === 'none') ? 'selected' : '' ?>>None</option>
                                <option value="fixed" <?= (($_POST['late_fee_type'] ?? '') === 'fixed') ? 'selected' : '' ?>>Fixed Surcharge (PKR)</option>
                                <option value="percentage" <?= (($_POST['late_fee_type'] ?? '') === 'percentage') ? 'selected' : '' ?>>Percentage Surcharge (%)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Fee Value</label>
                            <input type="number" step="0.01" name="late_fee_value" class="form-control form-control-sm" value="<?= e($_POST['late_fee_value'] ?? '0.00') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description / Policy Details</label>
                            <input type="text" name="description" class="form-control form-control-sm" value="<?= e($_POST['description'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Plan Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
