<?php
/**
 * HSMS - Master Layout Header
 * Emits HTML layout header and navigation shell
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_login();

$currentUser = current_user();
$activeSociety = get_active_society();
$userSocieties = get_user_societies($currentUser['id'], $currentUser['is_super_admin']);
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> — <?= e(APP_SHORT_NAME) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <!-- Custom Executive Theme -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div id="app-wrapper">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div id="content-wrapper">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="dropdown">
                    <button class="society-badge-pill dropdown-toggle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-buildings"></i>
                        <span><?= e($activeSociety['name'] ?? 'Select Society') ?> (<?= e($activeSociety['society_code'] ?? '—') ?>)</span>
                    </button>
                    <ul class="dropdown-menu shadow-sm">
                        <li class="dropdown-header text-uppercase small fw-bold">Switch Society</li>
                        <?php foreach ($userSocieties as $soc): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between <?= ((int)$soc['id'] === (int)($activeSociety['id'] ?? 0)) ? 'active fw-bold' : '' ?>" 
                                   href="<?= base_url('switch_society.php?id=' . (int)$soc['id'] . '&csrf=' . csrf_token()) ?>">
                                    <span><?= e($soc['name']) ?></span>
                                    <span class="badge bg-secondary ms-2"><?= e($soc['society_code']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- User Menu -->
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                        </div>
                        <span class="d-none d-sm-inline fw-semibold"><?= e($currentUser['name']) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header">
                            <div class="fw-bold"><?= e($currentUser['name']) ?></div>
                            <div class="small text-muted"><?= e($currentUser['role_name']) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('modules/settings/profile.php') ?>"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('modules/settings/index.php') ?>"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Container -->
        <main class="page-container">
            <!-- Flash Message Banner -->
            <?php if (has_flash()): ?>
                <?php foreach (get_flash() as $flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
