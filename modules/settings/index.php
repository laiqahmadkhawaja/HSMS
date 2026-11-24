<?php
/**
 * HSMS - General ERP System & Society Settings
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('settings_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

// Handle Society Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_society') {
        $name     = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $regNo    = trim($_POST['registration_number'] ?? '');
        $remarks  = trim($_POST['remarks'] ?? '');

        if (empty($name) || empty($location)) {
            $errors[] = 'Society Name and Location are required.';
        } else {
            $stmtUpd = $db->prepare("
                UPDATE `societies`
                SET `name` = :n, `location` = :loc, `address` = :addr, `phone` = :ph,
                    `email` = :em, `registration_number` = :reg, `remarks` = :rem
                WHERE `id` = :id
            ");
            $stmtUpd->execute([
                'n'    => $name,
                'loc'  => $location,
                'addr' => $address,
                'ph'   => $phone,
                'em'   => $email,
                'reg'  => $regNo,
                'rem'  => $remarks,
                'id'   => $activeSocId
            ]);
            log_audit('SOCIETY_SETTINGS_UPDATED', 'societies', $activeSocId, null, ['name' => $name]);
            redirect_with_message(base_url('modules/settings/index.php'), 'Society details updated successfully!', 'success');
        }
    }
}

// Refresh active society record
$society = get_active_society();

$pageTitle = 'Society & System Configuration';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Society & System Settings</h4>
        <p class="text-muted small mb-0">Global business policies, organizational branding, and operational parameters</p>
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

<div class="row g-4">
    <!-- Active Society Configuration -->
    <div class="col-lg-7">
        <div class="card-hs h-100">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-buildings fs-5 me-2"></i>
                <span class="fw-bold">Active Society Information (<?= e($society['society_code'] ?? '—') ?>)</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_society">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Society Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm" required value="<?= e($society['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Society Code</label>
                            <input type="text" class="form-control form-control-sm bg-light font-monospace" value="<?= e($society['society_code'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location / City <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-control-sm" required value="<?= e($society['location'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Registration / NOC Number</label>
                            <input type="text" name="registration_number" class="form-control form-control-sm" value="<?= e($society['registration_number'] ?? '') ?>" placeholder="e.g. NOC-RDA-2024-001">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Physical Address</label>
                        <input type="text" name="address" class="form-control form-control-sm" value="<?= e($society['address'] ?? '') ?>" placeholder="Site Office Address">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control form-control-sm" value="<?= e($society['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Official Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="<?= e($society['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">General Remarks / Tagline</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"><?= e($society['remarks'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-hs-primary btn-sm px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Update Society Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ERP Environment & Accounting Parameters -->
    <div class="col-lg-5">
        <div class="card-hs h-100">
            <div class="card-header bg-dark text-white d-flex align-items-center">
                <i class="bi bi-sliders fs-5 me-2"></i>
                <span class="fw-bold">Global System Constants & Rules</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Application Name:</span>
                        <span class="fw-bold"><?= e(APP_NAME) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">ERP Currency:</span>
                        <span class="fw-bold"><?= e(CURRENCY_NAME) ?> (<?= e(CURRENCY_SYMBOL) ?>)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Default Grace Period:</span>
                        <span class="badge bg-light text-dark border">7 Days</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Cancellation Retention:</span>
                        <span class="badge bg-light text-dark border">10% Standard</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Accounting Standard:</span>
                        <span class="badge bg-success">GAAP Double-Entry</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">PHP Environment:</span>
                        <span class="font-monospace">PHP <?= PHP_VERSION ?> (MariaDB 10.4)</span>
                    </li>
                </ul>

                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-shield-check me-1"></i> Financial parameters enforce decimal arithmetic (`DECIMAL(15,2)`) to eliminate floating-point penny rounding discrepancies.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>