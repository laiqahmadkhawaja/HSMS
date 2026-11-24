<?php
/**
 * HSMS - Create Housing Society
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('societies_manage');

$db = get_db_connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $code = strtoupper(trim($_POST['society_code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $regNo = trim($_POST['registration_number'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('society_code', 'Society Code')
              ->required('name', 'Society Name')
              ->required('location', 'Location')
              ->required('contact_number', 'Contact Number');

    if ($email !== '') {
        $validator->email('email', 'Email Address');
    }

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Check duplicate code
        $stmt = $db->prepare("SELECT COUNT(*) FROM `societies` WHERE `society_code` = :code");
        $stmt->execute(['code' => $code]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['society_code'] = 'This society code is already in use.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO `societies` (`society_code`, `name`, `location`, `address`, `contact_number`, `email`, `registration_number`, `start_date`, `status`, `remarks`)
                VALUES (:code, :name, :location, :address, :contact, :email, :reg_no, :sdate, :status, :remarks)
            ");
            $stmt->execute([
                'code'     => $code,
                'name'     => $name,
                'location' => $location,
                'address'  => $address ?: null,
                'contact'  => $contact,
                'email'    => $email ?: null,
                'reg_no'   => $regNo ?: null,
                'sdate'    => $startDate ?: null,
                'status'   => $status,
                'remarks'  => $remarks ?: null
            ]);

            $newId = (int)$db->lastInsertId();
            // Automatically grant Super Admin and current user access to this society
            $user = current_user();
            if ($user) {
                $db->prepare("INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)")
                   ->execute(['uid' => $user['id'], 'sid' => $newId]);
            }

            log_audit('SOCIETY_CREATED', 'societies', $newId, null, ['name' => $name, 'code' => $code], $newId);
            redirect_with_message(base_url('modules/societies/index.php'), "Society '{$name}' created successfully!", 'success');
        }
    }
}

$pageTitle = 'Add New Society';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-buildings me-2"></i> Register New Housing Society / Project</span>
                <a href="<?= base_url('modules/societies/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to List</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Society Code <span class="text-danger">*</span></label>
                            <input type="text" name="society_code" class="form-control form-control-sm <?= isset($errors['society_code']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. BHR-01" required value="<?= e($_POST['society_code'] ?? '') ?>">
                            <?php if (isset($errors['society_code'])): ?><div class="invalid-feedback"><?= e($errors['society_code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Society / Project Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Bahria Meadows Islamabad" required value="<?= e($_POST['name'] ?? '') ?>">
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Location / City <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-control-sm <?= isset($errors['location']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Rawalpindi / Islamabad" required value="<?= e($_POST['location'] ?? '') ?>">
                            <?php if (isset($errors['location'])): ?><div class="invalid-feedback"><?= e($errors['location']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Official Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="contact_number" class="form-control form-control-sm <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>" 
                                   placeholder="+92 51 1234567" required value="<?= e($_POST['contact_number'] ?? '') ?>">
                            <?php if (isset($errors['contact_number'])): ?><div class="invalid-feedback"><?= e($errors['contact_number']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Official Email</label>
                            <input type="email" name="email" class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   placeholder="info@society.com" value="<?= e($_POST['email'] ?? '') ?>">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Registration / NOC Number</label>
                            <input type="text" name="registration_number" class="form-control form-control-sm" 
                                   placeholder="e.g. RDA/MP&TE/2024/44" value="<?= e($_POST['registration_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Launch / Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($_POST['start_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Operational Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Physical / Site Address</label>
                        <textarea name="address" class="form-control form-control-sm" rows="2" placeholder="Sector, Avenue, Expressway..."><?= e($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Internal Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"><?= e($_POST['remarks'] ?? '') ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary btn-sm px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Society
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
