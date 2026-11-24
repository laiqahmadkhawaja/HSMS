<?php
/**
 * HSMS - Edit System User
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('users_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$errors = [];

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    redirect_with_message(base_url('modules/users/index.php'), 'Invalid user specified.', 'danger');
}

$stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect_with_message(base_url('modules/users/index.php'), 'User not found.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $roleId   = (int)($_POST['role_id'] ?? 0);
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $isSuper  = !empty($_POST['is_super_admin']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');
    $socIds   = $_POST['society_ids'] ?? [];

    if (empty($name) || empty($email) || $roleId <= 0) {
        $errors[] = 'Full Name, Email, and Role are required.';
    }

    if (empty($errors)) {
        $stmtC = $db->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = :e AND `id` != :id");
        $stmtC->execute(['e' => $email, 'id' => $userId]);
        if ((int)$stmtC->fetchColumn() > 0) {
            $errors[] = 'This email is already in use by another account.';
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE `users` SET `name` = :n, `email` = :e, `phone` = :p, `role_id` = :r, `is_super_admin` = :super, `status` = :st";
        $params = [
            'n'     => $name,
            'e'     => $email,
            'p'     => $phone,
            'r'     => $roleId,
            'super' => $isSuper,
            'st'    => $status,
            'id'    => $userId
        ];

        if (!empty($password)) {
            $sql .= ", `password_hash` = :hash";
            $params['hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $sql .= " WHERE `id` = :id";
        $stmtUpd = $db->prepare($sql);
        $stmtUpd->execute($params);

        // Update society access
        $db->prepare("DELETE FROM `user_society_access` WHERE `user_id` = :uid")->execute(['uid' => $userId]);
        if (!empty($socIds) && is_array($socIds)) {
            $stmtSoc = $db->prepare("INSERT INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)");
            foreach ($socIds as $sid) {
                $stmtSoc->execute(['uid' => $userId, 'sid' => (int)$sid]);
            }
        }

        log_audit('USER_UPDATED', 'users', $userId, null, ['name' => $name, 'role_id' => $roleId]);
        redirect_with_message(base_url('modules/users/index.php'), 'User account updated successfully!', 'success');
    }
}

// Fetch user's currently assigned societies
$userSocIds = $db->prepare("SELECT society_id FROM `user_society_access` WHERE `user_id` = :uid");
$userSocIds->execute(['uid' => $userId]);
$assignedSocs = $userSocIds->fetchAll(PDO::FETCH_COLUMN);

$roles = $db->query("SELECT * FROM `roles` ORDER BY `id` ASC")->fetchAll();
$societies = $db->query("SELECT id, name, society_code FROM `societies` WHERE `status` = 'active' ORDER BY `name` ASC")->fetchAll();

$pageTitle = 'Edit User — ' . $user['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Edit User Account</h4>
        <p class="text-muted small mb-0">Username: <strong><?= e($user['username']) ?></strong></p>
    </div>
    <a href="<?= base_url('modules/users/index.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to User Directory
    </a>
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

<div class="card-hs max-w-800">
    <div class="card-header bg-dark text-white">
        <span class="fw-bold"><i class="bi bi-pencil-square me-1"></i> Edit Credentials & Privileges</span>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required value="<?= e($_POST['name'] ?? $user['name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" class="form-control form-control-sm bg-light font-monospace" value="<?= e($user['username']) ?>" readonly>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control form-control-sm" required value="<?= e($_POST['email'] ?? $user['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control form-control-sm" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Role Assignment <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select form-select-sm" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= ((int)($user['role_id']) === (int)$r['id']) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Account Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="active" <?= ($user['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($user['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Reset Password</label>
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Leave blank to keep current">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Super Admin Privileges</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="superAdminCheck" <?= !empty($user['is_super_admin']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="superAdminCheck">
                        Grant Unrestricted Super Admin Access
                    </label>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold">Permitted Societies</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($societies as $s): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="society_ids[]" value="<?= (int)$s['id'] ?>" id="soc_<?= (int)$s['id'] ?>" <?= (in_array((int)$s['id'], $assignedSocs, true)) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="soc_<?= (int)$s['id'] ?>">
                                <?= e($s['name']) ?> (<?= e($s['society_code']) ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="text-end">
                <a href="<?= base_url('modules/users/index.php') ?>" class="btn btn-secondary btn-sm me-2">Cancel</a>
                <button type="submit" class="btn btn-hs-primary btn-sm px-4 fw-semibold">
                    <i class="bi bi-save me-1"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>