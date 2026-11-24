<?php
/**
 * HSMS - Master Layout Sidebar
 */

declare(strict_types=1);

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
function is_active_route(string $needle, string $currentUri): string {
    return (str_contains($currentUri, $needle)) ? 'active' : '';
}
?>
<aside id="sidebar">
    <a href="<?= base_url('dashboard.php') ?>" class="sidebar-brand">
        <i class="bi bi-shield-fill-check text-primary fs-4"></i>
        <span>HSMS <span class="badge bg-primary text-white fs-6">ERP</span></span>
    </a>

    <ul class="sidebar-nav">
        <li>
            <a href="<?= base_url('dashboard.php') ?>" class="nav-link <?= is_active_route('dashboard.php', $currentUri) ?>">
                <i class="bi bi-speedometer2 nav-icon"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-heading">Master Setup</li>
        <li>
            <a href="<?= base_url('modules/societies/index.php') ?>" class="nav-link <?= is_active_route('/societies/', $currentUri) ?>">
                <i class="bi bi-buildings nav-icon"></i>
                <span>Societies</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/phases/index.php') ?>" class="nav-link <?= is_active_route('/phases/', $currentUri) ?>">
                <i class="bi bi-diagram-3 nav-icon"></i>
                <span>Phases</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/blocks/index.php') ?>" class="nav-link <?= is_active_route('/blocks/', $currentUri) ?>">
                <i class="bi bi-grid-3x3 nav-icon"></i>
                <span>Blocks</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/plots/index.php') ?>" class="nav-link <?= is_active_route('/plots/', $currentUri) ?>">
                <i class="bi bi-geo-alt nav-icon"></i>
                <span>Plot Inventory</span>
            </a>
        </li>

        <li class="sidebar-heading">Sales & Customers</li>
        <li>
            <a href="<?= base_url('modules/customers/index.php') ?>" class="nav-link <?= is_active_route('/customers/', $currentUri) ?>">
                <i class="bi bi-people nav-icon"></i>
                <span>Customer Registry</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/installment_plans/index.php') ?>" class="nav-link <?= is_active_route('/installment_plans/', $currentUri) ?>">
                <i class="bi bi-calendar-check nav-icon"></i>
                <span>Payment Plans</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/bookings/index.php') ?>" class="nav-link <?= is_active_route('/bookings/', $currentUri) ?>">
                <i class="bi bi-card-checklist nav-icon"></i>
                <span>Plot Bookings</span>
            </a>
        </li>

        <li class="sidebar-heading">Recovery & Operations</li>
        <li>
            <a href="<?= base_url('modules/recoveries/index.php') ?>" class="nav-link <?= is_active_route('/recoveries/index', $currentUri) ?>">
                <i class="bi bi-cash-stack nav-icon"></i>
                <span>Recovery Desk</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/receipts/index.php') ?>" class="nav-link <?= is_active_route('/receipts/', $currentUri) ?>">
                <i class="bi bi-receipt nav-icon"></i>
                <span>Receipt Register</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/employees/recovery_officers.php') ?>" class="nav-link <?= is_active_route('/employees/', $currentUri) ?>">
                <i class="bi bi-person-badge nav-icon"></i>
                <span>Recovery Officers</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/recoveries/promises.php') ?>" class="nav-link <?= is_active_route('/promises', $currentUri) ?>">
                <i class="bi bi-clock-history nav-icon"></i>
                <span>Promises to Pay</span>
            </a>
        </li>

        <li class="sidebar-heading">Transfers & Cancellations</li>
        <li>
            <a href="<?= base_url('modules/transfers/index.php') ?>" class="nav-link <?= is_active_route('/transfers/', $currentUri) ?>">
                <i class="bi bi-arrow-left-right nav-icon"></i>
                <span>Plot Transfers</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/cancellations/index.php') ?>" class="nav-link <?= is_active_route('/cancellations/', $currentUri) ?>">
                <i class="bi bi-x-circle nav-icon"></i>
                <span>Cancellations & Refunds</span>
            </a>
        </li>

        <li class="sidebar-heading">Accounting & Finance</li>
        <li>
            <a href="<?= base_url('modules/accounts/index.php') ?>" class="nav-link <?= is_active_route('/accounts/index', $currentUri) ?>">
                <i class="bi bi-diagram-2 nav-icon"></i>
                <span>Chart of Accounts</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/accounts/journal_entries.php') ?>" class="nav-link <?= is_active_route('/journal_entries', $currentUri) ?>">
                <i class="bi bi-journal-text nav-icon"></i>
                <span>Journal Entries</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/expenses/index.php') ?>" class="nav-link <?= is_active_route('/expenses/', $currentUri) ?>">
                <i class="bi bi-wallet2 nav-icon"></i>
                <span>Expenses Management</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/accounts/cash_bank.php') ?>" class="nav-link <?= is_active_route('/cash_bank', $currentUri) ?>">
                <i class="bi bi-bank nav-icon"></i>
                <span>Cash & Bank Books</span>
            </a>
        </li>

        <li class="sidebar-heading">Reports & System</li>
        <li>
            <a href="<?= base_url('modules/reports/index.php') ?>" class="nav-link <?= is_active_route('/reports/', $currentUri) ?>">
                <i class="bi bi-file-earmark-bar-graph nav-icon"></i>
                <span>Reports Center</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/users/index.php') ?>" class="nav-link <?= is_active_route('/users/', $currentUri) ?>">
                <i class="bi bi-shield-lock nav-icon"></i>
                <span>Users & Roles</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/settings/notifications.php') ?>" class="nav-link <?= is_active_route('/notifications', $currentUri) ?>">
                <i class="bi bi-chat-left-dots nav-icon"></i>
                <span>Notifications & SMS</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/settings/audit_logs.php') ?>" class="nav-link <?= is_active_route('/audit_logs', $currentUri) ?>">
                <i class="bi bi-list-columns-reverse nav-icon"></i>
                <span>Audit Logs</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('modules/settings/backup.php') ?>" class="nav-link <?= is_active_route('/backup', $currentUri) ?>">
                <i class="bi bi-database-down nav-icon"></i>
                <span>Database Backup</span>
            </a>
        </li>
    </ul>
</aside>
