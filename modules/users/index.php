<?php
/**
 * HSMS - Users & Roles Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('users_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$errors = [];

// Handle user creation / status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_user') {
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

        // Check unique username and email
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

            // Insert society access
            if (!empty($socIds) && is_array($socIds)) {
                $stmtSoc = $db->prepare("INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)");
                foreach ($socIds as $sid) {
                    $stmtSoc->execute(['uid' => $newUserId, 'sid' => (int)$sid]);
                }
            } else {
                // Default to current active society
                $stmtSoc = $db->prepare("INSERT IGNORE INTO `user_society_access` (`user_id`, `society_id`) VALUES (:uid, :sid)");
                $stmtSoc->execute(['uid' => $newUserId, 'sid' => $activeSocId]);
            }

            log_audit('USER_CREATED', 'users', $newUserId, null, ['username' => $username, 'role_id' => $roleId]);
            redirect_with_message(base_url('modules/users/index.php'), 'User account created successfully!', 'success');
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $currStatus = trim($_POST['current_status'] ?? 'active');
        $newStatus = ($currStatus === 'active') ? 'inactive' : 'active';

        if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
            $errors[] = 'You cannot deactivate your own active account.';
        } else {
            $stmtUpd = $db->prepare("UPDATE `users` SET `status` = :st WHERE `id` = :uid");
            $stmtUpd->execute(['st' => $newStatus, 'uid' => $userId]);
            log_audit('USER_STATUS_TOGGLED', 'users', $userId, ['old_status' => $currStatus], ['new_status' => $newStatus]);
            redirect_with_message(base_url('modules/users/index.php'), "User status updated to {$newStatus}.", 'success');
        }
    }
}

// Fetch all users
$stmtUsers = $db->query("
    SELECT u.*, r.name as role_name,
           GROUP_CONCAT(s.society_code SEPARATOR ', ') as societies_list
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    LEFT JOIN `user_society_access` usa ON u.id = usa.user_id
    LEFT JOIN `societies` s ON usa.society_id = s.id
    GROUP BY u.id
    ORDER BY u.id ASC
");
$users = $stmtUsers->fetchAll();

// Fetch roles for selector
$roles = $db->query("SELECT * FROM `roles` ORDER BY `id` ASC")->fetchAll();

// Fetch societies for selector
$societies = $db->query("SELECT id, name, society_code FROM `societies` WHERE `status` = 'active' ORDER BY `name` ASC")->fetchAll();

$pageTitle = 'Users & Roles Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Users & Access Control</h4>
        <p class="text-muted small mb-0">System operators, RBAC assignments, and society authorizations</p>
    </div>
    <button class="btn btn-hs-primary btn-sm px-3" data-bs-toggle="collapse" data-bs-target="#createUserCard">
        <i class="bi bi-person-plus me-1"></i> Add New User
    </button>
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

<!-- Create User Form Collapse -->
<div class="collapse mb-4" id="createUserCard">
    <div class="card-hs">
        <div class="card-header bg-primary text-white">
            <span class="fw-bold"><i class="bi bi-person-plus me-1"></i> Register New System User</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Asif Raza">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control form-control-sm" required placeholder="e.g. asif_sales">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" required placeholder="asif@society.com">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Phone Number</label>
                        <input type="text" name="phone" class="form-control form-control-sm" placeholder="03001234567">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control form-control-sm" required placeholder="Minimum 6 characters">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">System Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select form-select-sm" required>
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Super Admin Privilege</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_super_admin" value="1" id="superAdminCheck">
                            <label class="form-check-label small" for="superAdminCheck">
                                Grant Unrestricted Super Admin
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Assigned Societies</label>
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
                    <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-toggle="collapse" data-bs-target="#createUserCard">Cancel</button>
                    <button type="submit" class="btn btn-hs-primary btn-sm px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i> Save User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card-hs">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-people-fill text-primary me-2"></i> Authorized User Directory</span>
        <span class="badge bg-light text-muted border"><?= count($users) ?> Total Accounts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>User Name</th>
                        <th>Username / Email</th>
                        <th>Role</th>
                        <th>Society Scope</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-muted small"><?= (int)$u['id'] ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($u['name']) ?></div>
                                <div class="text-muted small"><?= e($u['phone'] ?: 'No phone') ?></div>
                            </td>
                            <td>
                                <div class="font-monospace text-primary"><?= e($u['username']) ?></div>
                                <div class="text-muted small"><?= e($u['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-dark"><?= e($u['role_name']) ?></span>
                                <?php if (!empty($u['is_super_admin'])): ?>
                                    <span class="badge bg-danger ms-1">Super Admin</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($u['is_super_admin']) ? '<span class="text-success fw-bold">All Societies (Unrestricted)</span>' : e($u['societies_list'] ?: 'None') ?>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($u['last_login']) ? date('d M Y, h:i A', strtotime($u['last_login'])) : 'Never logged in' ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= base_url('modules/users/edit.php?id=' . (int)$u['id']) ?>" class="btn btn-outline-primary btn-sm py-0 px-2 me-1" title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Change status for user <?= e($u['username']) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= e($u['status']) ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Toggle Status">
                                            <?= ($u['status'] === 'active') ? '<i class="bi bi-pause-circle text-warning"></i> Deactivate' : '<i class="bi bi-play-circle text-success"></i> Activate' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>