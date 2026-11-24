<?php
/**
 * HSMS - User Authentication Login Interface
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

// If user is already logged in, redirect directly to dashboard before emitting HTML
if (is_logged_in()) {
    redirect(base_url('dashboard.php'));
}

$errorMessage = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = (string)($_POST['password'] ?? '');

    if ($usernameInput === '' || $passwordInput === '') {
        $errorMessage = 'Please enter both username/email and password.';
    } else {
        if (login_user($usernameInput, $passwordInput)) {
            redirect_with_message(base_url('dashboard.php'), 'Welcome back to ' . APP_SHORT_NAME . '!', 'success');
        } else {
            $errorMessage = 'Invalid username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= e(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width: 54px; height: 54px; font-size: 1.6rem;">
            <i class="bi bi-shield-fill-check"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1"><?= e(APP_NAME) ?></h4>
        <p class="text-muted small mb-0">Enter your credentials to access the enterprise portal</p>
    </div>

    <div class="login-body">
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 small" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
                <div><?= e($errorMessage) ?></div>
            </div>
        <?php endif; ?>

        <?php if (has_flash()): ?>
            <?php foreach (get_flash() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 small" role="alert">
                    <?= e($flash['message']) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" action="<?= base_url('login.php') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control" required autofocus 
                           placeholder="admin" value="<?= e($usernameInput) ?>">
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">Password</label>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" required placeholder="admin123">
                </div>
            </div>

            <button type="submit" class="btn btn-hs-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Account
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center text-muted small">
            <div>Default Admin Login: <strong>admin</strong> / <strong>admin123</strong></div>
            <div class="mt-1">Staff Demo: <strong>tariq_sales</strong> / <strong>password123</strong></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
