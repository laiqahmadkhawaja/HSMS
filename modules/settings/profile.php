<?php
/**
 * HSMS - User Profile & Password Security
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_login();

$db = get_db_connection();
$userId = (int)$_SESSION['user_id'];
$errors = [];

// Fetch fresh user data
$stmt = $db->prepare("SELECT u.*, r.name as role_name FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE u.id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User session invalid.");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            $errors[] = 'Full Name and Email Address are required.';
        } else {
            // Check email uniqueness among other users
            $stmtC = $db->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = :e AND `id` != :id");
            $stmtC->execute(['e' => $email, 'id' => $userId]);
            if ((int)$stmtC->fetchColumn() > 0) {
                $errors[] = 'This email is already registered to another user account.';
            } else {
                $stmtUpd = $db->prepare("UPDATE `users` SET `name` = :n, `email` = :e, `phone` = :p WHERE `id` = :id");
                $stmtUpd->execute(['n' => $name, 'e' => $email, 'p' => $phone, 'id' => $userId]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                log_audit('PROFILE_UPDATED', 'users', $userId, null, ['name' => $name, 'email' => $email]);
                redirect_with_message(base_url('modules/settings/profile.php'), 'Profile updated successfully!', 'success');
            }
        }
    } elseif ($action === 'change_password') {
        $currPass = (string)($_POST['current_password'] ?? '');
        $newPass  = (string)($_POST['new_password'] ?? '');
        $confPass = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currPass, $user['password_hash'])) {
            $errors[] = 'The current password you provided is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters in length.';
        } elseif ($newPass !== $confPass) {
            $errors[] = 'New password and confirmation password do not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE `users` SET `password_hash` = :h WHERE `id` = :id");
            $stmtUpd->execute(['h' => $newHash, 'id' => $userId]);
            log_audit('PASSWORD_CHANGED', 'users', $userId);
            redirect_with_message(base_url('modules/settings/profile.php'), 'Password updated successfully!', 'success');
        }
    }
}

$pageTitle = 'My Profile & Security';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">My Account & Security</h4>
        <p class="text-muted small mb-0">Manage your profile details, contact information, and authentication credentials</p>
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
    <!-- Profile Info Form -->
    <div class="col-lg-6">
        <div class="card-hs h-100">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-person-badge fs-5 me-2"></i>
                <span class="fw-bold">Operator Profile Information</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" class="form-control form-control-sm bg-light font-monospace" value="<?= e($user['username']) ?>" readonly>
                        <span class="text-muted small">Username cannot be changed.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Assigned Role</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= e($user['role_name']) ?><?= $user['is_super_admin'] ? ' (Super Admin)' : '' ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" required value="<?= e($_POST['name'] ?? $user['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm" required value="<?= e($_POST['email'] ?? $user['email']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Mobile / Phone Number</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
                    </div>

                    <button type="submit" class="btn btn-hs-primary btn-sm px-4 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-6">
        <div class="card-hs h-100">
            <div class="card-header bg-dark text-white d-flex align-items-center">
                <i class="bi bi-shield-lock fs-5 me-2"></i>
                <span class="fw-bold">Change Account Password</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control form-control-sm" required placeholder="Enter current password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control form-control-sm" required placeholder="Minimum 6 characters">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control form-control-sm" required placeholder="Re-type new password">
                    </div>

                    <button type="submit" class="btn btn-outline-dark btn-sm px-4 fw-semibold">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>