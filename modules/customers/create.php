<?php
/**
 * HSMS - Register New Customer / Buyer
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('customers_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$errors = [];

// Determine next customer ID code
$stmtLast = $db->query("SELECT MAX(id) FROM `customers`");
$nextNum = ((int)$stmtLast->fetchColumn()) + 1;
$suggestedCode = sprintf('CUST-%04d', $nextNum);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $code = strtoupper(trim($_POST['customer_id_code'] ?? $suggestedCode));
    $name = trim($_POST['full_name'] ?? '');
    $fatherName = trim($_POST['father_husband_name'] ?? '');
    $cnic = trim($_POST['cnic_passport'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $resAddress = trim($_POST['residential_address'] ?? '');
    $permAddress = trim($_POST['permanent_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'Pakistan');

    // Nominee
    $nomineeName = trim($_POST['nominee_name'] ?? '');
    $nomineeRelation = trim($_POST['nominee_relation'] ?? '');
    $nomineeCnic = trim($_POST['nominee_cnic'] ?? '');
    $nomineeContact = trim($_POST['nominee_contact'] ?? '');

    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'blacklisted']) ? $_POST['status'] : 'active';
    $remarks = trim($_POST['remarks'] ?? '');
    $allowDupOverride = isset($_POST['allow_duplicate_override']) && $_POST['allow_duplicate_override'] === '1';

    $validator = new Validator($_POST);
    $validator->required('customer_id_code', 'Customer ID')
              ->required('full_name', 'Full Name')
              ->required('father_husband_name', 'Father / Husband Name')
              ->required('cnic_passport', 'CNIC / Passport')
              ->cnic('cnic_passport', 'CNIC')
              ->required('mobile', 'Mobile Number')
              ->mobile('mobile', 'Mobile Number')
              ->required('residential_address', 'Residential Address')
              ->required('city', 'City');

    if ($email !== '') {
        $validator->email('email', 'Email Address');
    }

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Check duplicate CNIC
        $stmtCnic = $db->prepare("SELECT id, full_name, customer_id_code FROM `customers` WHERE `cnic_passport` = :cnic");
        $stmtCnic->execute(['cnic' => $cnic]);
        $existing = $stmtCnic->fetch();

        if ($existing && !$allowDupOverride) {
            $errors['cnic_passport'] = "A customer with CNIC '{$cnic}' already exists: {$existing['full_name']} ({$existing['customer_id_code']}). Check the box below if this is an authorized re-registration.";
        } else {
            // Handle Profile Photo Upload if provided
            $photoPath = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['photo']['tmp_name']);

                if (in_array($mime, $allowedMimes, true) && $_FILES['photo']['size'] <= 3 * 1024 * 1024) {
                    $ext = match($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        default      => 'jpg'
                    };
                    $fileName = 'cust_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetDir = UPLOADS_PATH . '/customers';
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0755, true);
                    }
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetDir . '/' . $fileName)) {
                        $photoPath = 'uploads/customers/' . $fileName;
                    }
                }
            }

            $stmt = $db->prepare("
                INSERT INTO `customers` (
                    `customer_id_code`, `full_name`, `father_husband_name`, `cnic_passport`,
                    `mobile`, `whatsapp`, `email`, `residential_address`, `permanent_address`,
                    `city`, `country`, `nominee_name`, `nominee_relation`, `nominee_cnic`,
                    `nominee_contact`, `photo_path`, `status`, `remarks`
                ) VALUES (
                    :code, :name, :fname, :cnic,
                    :mobile, :wa, :email, :res_addr, :perm_addr,
                    :city, :country, :nom_name, :nom_rel, :nom_cnic,
                    :nom_phone, :photo, :status, :remarks
                )
            ");
            $stmt->execute([
                'code'      => $code,
                'name'      => $name,
                'fname'     => $fatherName,
                'cnic'      => $cnic,
                'mobile'    => $mobile,
                'wa'        => $whatsapp ?: null,
                'email'     => $email ?: null,
                'res_addr'  => $resAddress,
                'perm_addr' => $permAddress ?: null,
                'city'      => $city,
                'country'   => $country,
                'nom_name'  => $nomineeName ?: null,
                'nom_rel'   => $nomineeRelation ?: null,
                'nom_cnic'  => $nomineeCnic ?: null,
                'nom_phone' => $nomineeContact ?: null,
                'photo'     => $photoPath,
                'status'    => $status,
                'remarks'   => $remarks ?: null
            ]);

            $newCustId = (int)$db->lastInsertId();
            log_audit('CUSTOMER_REGISTERED', 'customers', $newCustId, null, ['name' => $name, 'cnic' => $cnic, 'code' => $code], $activeSocId);
            redirect_with_message(base_url('modules/customers/view.php?id=' . $newCustId), "Customer {$name} registered successfully!", 'success');
        }
    }
}

$pageTitle = 'Register Customer';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Register New Customer / Plot Buyer</span>
                <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Registry</a>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-vcard me-1"></i> Personal & Identification Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Customer ID Code <span class="text-danger">*</span></label>
                            <input type="text" name="customer_id_code" class="form-control form-control-sm <?= isset($errors['customer_id_code']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['customer_id_code'] ?? $suggestedCode) ?>">
                            <?php if (isset($errors['customer_id_code'])): ?><div class="invalid-feedback"><?= e($errors['customer_id_code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Full Name of Applicant <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control form-control-sm <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Muhammad Asif" required value="<?= e($_POST['full_name'] ?? '') ?>">
                            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Father / Husband Name <span class="text-danger">*</span></label>
                            <input type="text" name="father_husband_name" class="form-control form-control-sm <?= isset($errors['father_husband_name']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Abdul Rasheed" required value="<?= e($_POST['father_husband_name'] ?? '') ?>">
                            <?php if (isset($errors['father_husband_name'])): ?><div class="invalid-feedback"><?= e($errors['father_husband_name']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">CNIC / NICOP / Passport <span class="text-danger">*</span></label>
                            <input type="text" name="cnic_passport" class="form-control form-control-sm <?= isset($errors['cnic_passport']) ? 'is-invalid' : '' ?>" 
                                   placeholder="37405-1234567-1" required value="<?= e($_POST['cnic_passport'] ?? '') ?>">
                            <?php if (isset($errors['cnic_passport'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['cnic_passport']) ?></div>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="allow_duplicate_override" id="allow_duplicate_override" value="1">
                                    <label class="form-check-label text-warning small fw-bold" for="allow_duplicate_override">
                                        Authorize Duplicate Override (Record will be logged in audit trail)
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mobile Contact <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" class="form-control form-control-sm <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>" 
                                   placeholder="03001234567" required value="<?= e($_POST['mobile'] ?? '') ?>">
                            <?php if (isset($errors['mobile'])): ?><div class="invalid-feedback"><?= e($errors['mobile']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WhatsApp Number</label>
                            <input type="text" name="whatsapp" class="form-control form-control-sm" placeholder="03001234567" value="<?= e($_POST['whatsapp'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   placeholder="buyer@gmail.com" value="<?= e($_POST['email'] ?? '') ?>">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Applicant Passport Photo (Max 3MB)</label>
                            <input type="file" name="photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Residential Address <span class="text-danger">*</span></label>
                            <textarea name="residential_address" class="form-control form-control-sm <?= isset($errors['residential_address']) ? 'is-invalid' : '' ?>" 
                                      rows="2" required><?= e($_POST['residential_address'] ?? '') ?></textarea>
                            <?php if (isset($errors['residential_address'])): ?><div class="invalid-feedback"><?= e($errors['residential_address']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Permanent Address</label>
                            <textarea name="permanent_address" class="form-control form-control-sm" rows="2"><?= e($_POST['permanent_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control form-control-sm <?= isset($errors['city']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Islamabad" required value="<?= e($_POST['city'] ?? '') ?>">
                            <?php if (isset($errors['city'])): ?><div class="invalid-feedback"><?= e($errors['city']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control form-control-sm" value="<?= e($_POST['country'] ?? 'Pakistan') ?>">
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-people me-1"></i> Nominee / Next of Kin Details</h6>
                    <div class="row g-3 mb-4 p-3 bg-light rounded border">
                        <div class="col-md-3">
                            <label class="form-label">Nominee Name</label>
                            <input type="text" name="nominee_name" class="form-control form-control-sm" placeholder="e.g. Fatima Asif" value="<?= e($_POST['nominee_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Relationship with Applicant</label>
                            <input type="text" name="nominee_relation" class="form-control form-control-sm" placeholder="e.g. Wife / Son" value="<?= e($_POST['nominee_relation'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee CNIC</label>
                            <input type="text" name="nominee_cnic" class="form-control form-control-sm" placeholder="37405-..." value="<?= e($_POST['nominee_cnic'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee Contact</label>
                            <input type="text" name="nominee_contact" class="form-control form-control-sm" placeholder="0300..." value="<?= e($_POST['nominee_contact'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="blacklisted" <?= (($_POST['status'] ?? '') === 'blacklisted') ? 'selected' : '' ?>>Blacklisted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Internal Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($_POST['remarks'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Register Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
