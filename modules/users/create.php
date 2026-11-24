<?php
/**
 * HSMS - Add New System User
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('users_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $roleId   = (int)($_POST['role_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $isSuper  = !empty($_POST['is_super_admin']) ? 1 : 0;
    $socIds   = $_POST['society_ids'] ?? [];

    if (empty($name) || empty($username) || empty($email) || empty($password) || $roleId <= 0) {
        $errors[] = 'Please fill all required fields (Name, Username, Email, Password, Role).';
    }

    if (empty($errors)) {
        $stmtC = $db->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :u OR `email` = :e");
        $stmtC->execute(['u' => $username, 'e' => $email]);
        if ((int)$stmtC->fetchColumn() > 0) {
            $errors[] = 'A user with this username or email already exists.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmtIns = $db->prepare("
            INSERT INTO `users` (`name`, `username`, `email`, `phone`, `password_hash`, `role_id`, `is_super_admin`, `status`, `created_at`)
            VALUES (:name, :u, :e, :p, :hash, :rid, :super, 'active', NOW())
        ");
        $stmtIns->execute([
            'name'  => $name,
            'u'     => $username,
            'e'     => $email,
            'p'     => $phone,
            'hash'  => $hash,
            'rid'   => $roleId,
            'super' => $isSuper
        ]);
        $newUserId = (int)$db->lastInsertId();

        if (!empty($socIds) && is_array($socIds)) {
            $stmtSoc = $db->prepare("INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)");
            foreach ($socIds as $sid) {
                $stmtSoc->execute(['uid' => $newUserId, 'sid' => (int)$sid]);
            }
        } else {
            $stmtSoc = $db->prepare("INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)");
            $stmtSoc->execute(['uid' => $newUserId, 'sid' => $activeSocId]);
        }

        log_audit('USER_CREATED', 'users', $newUserId, null, ['username' => $username, 'role_id' => $roleId]);
        redirect_with_message(base_url('modules/users/index.php'), 'User account created successfully!', 'success');
    }
}

$roles = $db->query("SELECT * FROM `roles` ORDER BY `id` ASC")->fetchAll();
$societies = $db->query("SELECT id, name, society_code FROM `societies` WHERE `status` = 'active' ORDER BY `name` ASC")->fetchAll();

$pageTitle = 'Add New System User';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Add New System Operator</h4>
        <p class="text-muted small mb-0">Create credentials and assign society authorizations</p>
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
    <div class="card-header bg-primary text-white">
        <span class="fw-bold"><i class="bi bi-person-plus me-1"></i> User Information</span>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Tariq Mehmood" value="<?= e($_POST['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control form-control-sm" required placeholder="e.g. tariq_sales" value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control form-control-sm" required placeholder="tariq@society.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control form-control-sm" placeholder="03001234567" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control form-control-sm" required placeholder="Minimum 6 characters">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Role Assignment <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select form-select-sm" required>
                        <option value="">Select Role...</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= ((int)($_POST['role_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Super Admin Privileges</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="superAdminCheck">
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
                            <input class="form-check-input" type="checkbox" name="society_ids[]" value="<?= (int)$s['id'] ?>" id="soc_<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === $activeSocId) ? 'checked' : '' ?>>
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
                    <i class="bi bi-save me-1"></i> Register User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>