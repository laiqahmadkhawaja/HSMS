<?php
/**
 * HSMS - Edit Customer Profile
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('customers_manage');

$db = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM `customers` WHERE `id` = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect_with_message(base_url('modules/customers/index.php'), 'Customer not found.', 'danger');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $code = strtoupper(trim($_POST['customer_id_code'] ?? $customer['customer_id_code']));
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

    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'blacklisted']) ? $_POST['status'] : $customer['status'];
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('full_name', 'Full Name')
              ->required('father_husband_name', 'Father / Husband Name')
              ->required('cnic_passport', 'CNIC / Passport')
              ->required('mobile', 'Mobile Number')
              ->required('residential_address', 'Residential Address')
              ->required('city', 'City');

    if ($email !== '') {
        $validator->email('email', 'Email Address');
    }

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Handle photo upload if new one uploaded
        $photoPath = $customer['photo_path'];
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
            UPDATE `customers` SET
                `customer_id_code` = :code,
                `full_name` = :name,
                `father_husband_name` = :fname,
                `cnic_passport` = :cnic,
                `mobile` = :mobile,
                `whatsapp` = :wa,
                `email` = :email,
                `residential_address` = :res_addr,
                `permanent_address` = :perm_addr,
                `city` = :city,
                `country` = :country,
                `nominee_name` = :nom_name,
                `nominee_relation` = :nom_rel,
                `nominee_cnic` = :nom_cnic,
                `nominee_contact` = :nom_phone,
                `photo_path` = :photo,
                `status` = :status,
                `remarks` = :remarks
            WHERE `id` = :id
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
            'remarks'   => $remarks ?: null,
            'id'        => $id
        ]);

        log_audit('CUSTOMER_UPDATED', 'customers', $id, $customer, ['name' => $name, 'cnic' => $cnic]);
        redirect_with_message(base_url('modules/customers/view.php?id=' . $id), "Customer profile updated successfully!", 'success');
    }
}

$pageTitle = 'Edit Customer: ' . $customer['full_name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Customer Profile</span>
                <a href="<?= base_url('modules/customers/view.php?id=' . $id) ?>" class="btn btn-sm btn-outline-secondary">View Profile</a>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-vcard me-1"></i> Personal & Identification Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Customer ID Code <span class="text-danger">*</span></label>
                            <input type="text" name="customer_id_code" class="form-control form-control-sm" required value="<?= e($_POST['customer_id_code'] ?? $customer['customer_id_code']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Full Name of Applicant <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control form-control-sm <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['full_name'] ?? $customer['full_name']) ?>">
                            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Father / Husband Name <span class="text-danger">*</span></label>
                            <input type="text" name="father_husband_name" class="form-control form-control-sm" required value="<?= e($_POST['father_husband_name'] ?? $customer['father_husband_name']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">CNIC / Passport <span class="text-danger">*</span></label>
                            <input type="text" name="cnic_passport" class="form-control form-control-sm" required value="<?= e($_POST['cnic_passport'] ?? $customer['cnic_passport']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mobile Contact <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" class="form-control form-control-sm" required value="<?= e($_POST['mobile'] ?? $customer['mobile']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WhatsApp Number</label>
                            <input type="text" name="whatsapp" class="form-control form-control-sm" value="<?= e($_POST['whatsapp'] ?? $customer['whatsapp']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="<?= e($_POST['email'] ?? $customer['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Update Passport Photo</label>
                            <input type="file" name="photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Residential Address <span class="text-danger">*</span></label>
                            <textarea name="residential_address" class="form-control form-control-sm" rows="2" required><?= e($_POST['residential_address'] ?? $customer['residential_address']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Permanent Address</label>
                            <textarea name="permanent_address" class="form-control form-control-sm" rows="2"><?= e($_POST['permanent_address'] ?? $customer['permanent_address']) ?></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control form-control-sm" required value="<?= e($_POST['city'] ?? $customer['city']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control form-control-sm" value="<?= e($_POST['country'] ?? $customer['country']) ?>">
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-people me-1"></i> Nominee / Next of Kin Details</h6>
                    <div class="row g-3 mb-4 p-3 bg-light rounded border">
                        <div class="col-md-3">
                            <label class="form-label">Nominee Name</label>
                            <input type="text" name="nominee_name" class="form-control form-control-sm" value="<?= e($_POST['nominee_name'] ?? $customer['nominee_name']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Relationship with Applicant</label>
                            <input type="text" name="nominee_relation" class="form-control form-control-sm" value="<?= e($_POST['nominee_relation'] ?? $customer['nominee_relation']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee CNIC</label>
                            <input type="text" name="nominee_cnic" class="form-control form-control-sm" value="<?= e($_POST['nominee_cnic'] ?? $customer['nominee_cnic']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee Contact</label>
                            <input type="text" name="nominee_contact" class="form-control form-control-sm" value="<?= e($_POST['nominee_contact'] ?? $customer['nominee_contact']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" <?= (($customer['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($customer['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="blacklisted" <?= (($customer['status']) === 'blacklisted') ? 'selected' : '' ?>>Blacklisted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Internal Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($_POST['remarks'] ?? $customer['remarks']) ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Customer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
