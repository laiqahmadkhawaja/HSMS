<?php
/**
 * HSMS - Edit Installment Plan Template
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('masters_manage');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM `installment_plan_templates` WHERE `id` = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$plan = $stmt->fetch();

if (!$plan) {
    redirect_with_message(base_url('modules/installment_plans/index.php'), 'Plan Template not found.', 'danger');
}

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
              ->required('plan_code', 'Plan Code');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        $stmt = $db->prepare("
            UPDATE `installment_plan_templates` SET
                `plan_name` = :name,
                `plan_code` = :code,
                `duration_months` = :dur,
                `down_payment_type` = :dpt,
                `down_payment_value` = :dpv,
                `frequency` = :freq,
                `total_installments` = :ti,
                `grace_period_days` = :grace,
                `late_fee_type` = :lft,
                `late_fee_value` = :lfv,
                `status` = :status,
                `description` = :desc
            WHERE `id` = :id
        ");
        $stmt->execute([
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
            'desc'   => $desc ?: null,
            'id'     => $id
        ]);

        log_audit('PLAN_TEMPLATE_UPDATED', 'installment_plans', $id, $plan, ['name' => $name, 'code' => $code]);
        redirect_with_message(base_url('modules/installment_plans/index.php'), "Plan Template '{$name}' updated successfully!", 'success');
    }
}

$pageTitle = 'Edit Plan Template: ' . $plan['plan_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Plan Template</span>
                <a href="<?= base_url('modules/installment_plans/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Templates</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" name="plan_name" class="form-control form-control-sm" required value="<?= e($_POST['plan_name'] ?? $plan['plan_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plan Code <span class="text-danger">*</span></label>
                            <input type="text" name="plan_code" class="form-control form-control-sm" required value="<?= e($_POST['plan_code'] ?? $plan['plan_code']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Total Duration (Months) <span class="text-danger">*</span></label>
                            <input type="number" name="duration_months" class="form-control form-control-sm" required value="<?= e($_POST['duration_months'] ?? $plan['duration_months']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Installment Frequency <span class="text-danger">*</span></label>
                            <select name="frequency" class="form-select form-select-sm">
                                <option value="monthly" <?= (($plan['frequency']) === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                                <option value="quarterly" <?= (($plan['frequency']) === 'quarterly') ? 'selected' : '' ?>>Quarterly</option>
                                <option value="semi_annual" <?= (($plan['frequency']) === 'semi_annual') ? 'selected' : '' ?>>Semi-Annual</option>
                                <option value="annual" <?= (($plan['frequency']) === 'annual') ? 'selected' : '' ?>>Annual</option>
                                <option value="custom" <?= (($plan['frequency']) === 'custom') ? 'selected' : '' ?>>Custom Schedule</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Down Payment Type</label>
                            <select name="down_payment_type" class="form-select form-select-sm">
                                <option value="percentage" <?= (($plan['down_payment_type']) === 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                                <option value="fixed" <?= (($plan['down_payment_type']) === 'fixed') ? 'selected' : '' ?>>Fixed Amount (PKR)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Down Payment Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="down_payment_value" class="form-control form-control-sm" required value="<?= e($_POST['down_payment_value'] ?? $plan['down_payment_value']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Installments Count <span class="text-danger">*</span></label>
                            <input type="number" name="total_installments" class="form-control form-control-sm" required value="<?= e($_POST['total_installments'] ?? $plan['total_installments']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Grace Period (Days)</label>
                            <input type="number" name="grace_period_days" class="form-control form-control-sm" value="<?= e($_POST['grace_period_days'] ?? $plan['grace_period_days']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Fee Policy</label>
                            <select name="late_fee_type" class="form-select form-select-sm">
                                <option value="none" <?= (($plan['late_fee_type']) === 'none') ? 'selected' : '' ?>>None</option>
                                <option value="fixed" <?= (($plan['late_fee_type']) === 'fixed') ? 'selected' : '' ?>>Fixed Surcharge (PKR)</option>
                                <option value="percentage" <?= (($plan['late_fee_type']) === 'percentage') ? 'selected' : '' ?>>Percentage Surcharge (%)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Fee Value</label>
                            <input type="number" step="0.01" name="late_fee_value" class="form-control form-control-sm" value="<?= e($_POST['late_fee_value'] ?? $plan['late_fee_value']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($plan['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($plan['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description / Policy Details</label>
                            <input type="text" name="description" class="form-control form-control-sm" value="<?= e($_POST['description'] ?? $plan['description']) ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
