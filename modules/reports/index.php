<?php
/**
 * HSMS - Reports Hub & Executive Center
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('reports_view');

$activeSocId = get_active_society_id();
$society = get_active_society();

$pageTitle = 'Reporting Center';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Executive Reports Center</h4>
        <p class="text-muted small mb-0">Financial, operational, and inventory statements for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-speedometer2 me-1"></i> Dashboard
    </a>
</div>

<div class="row g-4">
    <!-- 1. Sales & Bookings -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded me-3 fs-4">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Sales Register</h6>
                    <span class="text-muted small">Plot bookings & commercial sales</span>
                </div>
            </div>
            <p class="text-muted small mb-3">Detailed log of all plot booking contracts, sales value, customer information, down payment status, and sales agent performance.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/reports/sales_register.php') ?>" class="btn btn-sm btn-outline-primary w-100 fw-semibold">
                    Open Sales Register <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. Plot Inventory -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded me-3 fs-4">
                    <i class="bi bi-grid-3x3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Plot Inventory & Occupancy</h6>
                    <span class="text-muted small">Phase & block stock matrix</span>
                </div>
            </div>
            <p class="text-muted small mb-3">Complete status breakdown of all plots across phases and blocks, including available units, reserved inventory, and sold plots.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/reports/inventory.php') ?>" class="btn btn-sm btn-outline-success w-100 fw-semibold">
                    View Inventory Matrix <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 3. Overdue Aging Analysis -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded me-3 fs-4">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Overdue Aging Analysis</h6>
                    <span class="text-muted small">Aging buckets: 30, 60, 90, 180+ days</span>
                </div>
            </div>
            <p class="text-muted small mb-3">Categorization of pending installment arrears by maturity aging brackets to identify critical recovery bottlenecks and overdue balances.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/reports/aging.php') ?>" class="btn btn-sm btn-outline-danger w-100 fw-semibold">
                    Analyze Overdue Aging <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 4. Persistent Defaulters -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-warning bg-opacity-10 text-warning rounded me-3 fs-4">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Defaulters List & Notices</h6>
                    <span class="text-muted small">Multi-installment default follow-up</span>
                </div>
            </div>
            <p class="text-muted small mb-3">Identifies persistent defaulters with 2+ consecutive unpaid installments, enabling legal notices, WhatsApp follow-ups, and field assignments.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/reports/defaulters.php') ?>" class="btn btn-sm btn-outline-warning w-100 fw-semibold">
                    View Defaulters Report <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 5. Cash & Bank Book -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-info bg-opacity-10 text-info rounded me-3 fs-4">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Cash & Bank Book</h6>
                    <span class="text-muted small">Inflow, outflow & closing balances</span>
                </div>
            </div>
            <p class="text-muted small mb-3">Real-time daily transaction journal for all designated cash on hand and commercial bank collection accounts.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/accounts/cash_bank.php') ?>" class="btn btn-sm btn-outline-info w-100 fw-semibold">
                    Open Cash & Bank Book <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 6. Trial Balance -->
    <div class="col-md-4">
        <div class="card-hs h-100 p-3">
            <div class="d-flex align-items-center mb-3">
                <div class="p-2 bg-dark bg-opacity-10 text-dark rounded me-3 fs-4">
                    <i class="bi bi-scale"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0">Society Trial Balance</h6>
                    <span class="text-muted small">Double-entry equilibrium audit</span>
                </div>
            </div>
            <p class="text-muted small mb-3">General ledger trial balance reconciling all asset, liability, equity, revenue, and operating expense accounts with zero mathematical variance.</p>
            <div class="mt-auto">
                <a href="<?= base_url('modules/accounts/trial_balance.php') ?>" class="btn btn-sm btn-outline-dark w-100 fw-semibold">
                    View Trial Balance <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
