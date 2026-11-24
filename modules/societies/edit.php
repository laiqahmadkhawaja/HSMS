<?php
/**
 * HSMS - Edit Housing Society
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('societies_manage');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM `societies` WHERE `id` = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$society = $stmt->fetch();

if (!$society) {
    redirect_with_message(base_url('modules/societies/index.php'), 'Society not found.', 'danger');
}

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
        // Check duplicate code (excluding current ID)
        $stmt = $db->prepare("SELECT COUNT(*) FROM `societies` WHERE `society_code` = :code AND `id` != :id");
        $stmt->execute(['code' => $code, 'id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['society_code'] = 'This society code is already in use by another record.';
        } else {
            $oldVals = $society;
            $stmt = $db->prepare("
                UPDATE `societies` SET 
                    `society_code` = :code,
                    `name` = :name,
                    `location` = :location,
                    `address` = :address,
                    `contact_number` = :contact,
                    `email` = :email,
                    `registration_number` = :reg_no,
                    `start_date` = :sdate,
                    `status` = :status,
                    `remarks` = :remarks
                WHERE `id` = :id
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
                'remarks'  => $remarks ?: null,
                'id'       => $id
            ]);

            log_audit('SOCIETY_UPDATED', 'societies', $id, $oldVals, ['name' => $name, 'code' => $code], $id);
            redirect_with_message(base_url('modules/societies/index.php'), "Society '{$name}' updated successfully!", 'success');
        }
    }
}

$pageTitle = 'Edit Society: ' . $society['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Society Details</span>
                <a href="<?= base_url('modules/societies/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to List</a>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Society Code <span class="text-danger">*</span></label>
                            <input type="text" name="society_code" class="form-control form-control-sm <?= isset($errors['society_code']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['society_code'] ?? $society['society_code']) ?>">
                            <?php if (isset($errors['society_code'])): ?><div class="invalid-feedback"><?= e($errors['society_code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Society / Project Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-sm <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['name'] ?? $society['name']) ?>">
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Location / City <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control form-control-sm <?= isset($errors['location']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['location'] ?? $society['location']) ?>">
                            <?php if (isset($errors['location'])): ?><div class="invalid-feedback"><?= e($errors['location']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Official Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="contact_number" class="form-control form-control-sm <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['contact_number'] ?? $society['contact_number']) ?>">
                            <?php if (isset($errors['contact_number'])): ?><div class="invalid-feedback"><?= e($errors['contact_number']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Official Email</label>
                            <input type="email" name="email" class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   value="<?= e($_POST['email'] ?? $society['email']) ?>">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Registration / NOC Number</label>
                            <input type="text" name="registration_number" class="form-control form-control-sm" 
                                   value="<?= e($_POST['registration_number'] ?? $society['registration_number']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Launch / Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" 
                                   value="<?= e($_POST['start_date'] ?? $society['start_date']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Operational Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($_POST['status'] ?? $society['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? $society['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Physical / Site Address</label>
                        <textarea name="address" class="form-control form-control-sm" rows="2"><?= e($_POST['address'] ?? $society['address']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Internal Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"><?= e($_POST['remarks'] ?? $society['remarks']) ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary btn-sm px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Society
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
