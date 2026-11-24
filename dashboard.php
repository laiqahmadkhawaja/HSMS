<?php
/**
 * HSMS - Executive Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_login();

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

// Query Dynamic Real Operational Metrics
// 1. Plot inventory counts
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_plots,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_plots,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_plots,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as booked_plots,
        SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_plots,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_plots
    FROM `plots`
    WHERE `society_id` = :soc_id
");
$stmt->execute(['soc_id' => $activeSocId]);
$plotStats = $stmt->fetch() ?: [
    'total_plots' => 0, 'available_plots' => 0, 'reserved_plots' => 0, 
    'booked_plots' => 0, 'sold_plots' => 0, 'cancelled_plots' => 0
];

// 2. Sales & Financial Metrics
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(net_sale_price), 0) as total_sale_value,
        COALESCE(SUM(total_paid), 0) as total_received,
        COALESCE(SUM(remaining_balance), 0) as total_receivable,
        COALESCE(SUM(advance_balance), 0) as customer_advance
    FROM `bookings`
    WHERE `society_id` = :soc_id AND `status` = 'active'
");
$stmt->execute(['soc_id' => $activeSocId]);
$saleStats = $stmt->fetch() ?: [
    'total_sale_value' => 0, 'total_received' => 0, 
    'total_receivable' => 0, 'customer_advance' => 0
];

// 3. Overdue installments
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(balance_due), 0) as total_overdue,
        COUNT(*) as overdue_installments_count
    FROM `installment_schedules` s
    JOIN `bookings` b ON s.booking_id = b.id
    WHERE b.society_id = :soc_id AND s.status IN ('pending', 'partially_paid') AND s.due_date < CURDATE()
");
$stmt->execute(['soc_id' => $activeSocId]);
$overdueStats = $stmt->fetch() ?: ['total_overdue' => 0, 'overdue_installments_count' => 0];

// 4. Today's & This Month's Recovery
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN payment_date = CURDATE() THEN payment_amount ELSE 0 END), 0) as today_recovery,
        COALESCE(SUM(CASE WHEN MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) THEN payment_amount ELSE 0 END), 0) as month_recovery
    FROM `payments`
    WHERE `society_id` = :soc_id AND `status` = 'posted'
");
$stmt->execute(['soc_id' => $activeSocId]);
$recoveryStats = $stmt->fetch() ?: ['today_recovery' => 0, 'month_recovery' => 0];

// 5. This Month Expenses
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as month_expenses
    FROM `expenses`
    WHERE `society_id` = :soc_id AND `approval_status` = 'approved'
      AND MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())
");
$stmt->execute(['soc_id' => $activeSocId]);
$monthExpenses = (float)$stmt->fetchColumn();

// 6. Recent 5 Bookings
$stmt = $db->prepare("
    SELECT b.*, c.full_name as customer_name, c.mobile as customer_mobile, p.plot_number, bl.block_name
    FROM `bookings` b
    JOIN `customers` c ON b.customer_id = c.id
    JOIN `plots` p ON b.plot_id = p.id
    JOIN `blocks` bl ON p.block_id = bl.id
    WHERE b.society_id = :soc_id
    ORDER BY b.id DESC
    LIMIT 5
");
$stmt->execute(['soc_id' => $activeSocId]);
$recentBookings = $stmt->fetchAll();

// 7. Recent 5 Recovery Receipts
$stmt = $db->prepare("
    SELECT pay.*, c.full_name as customer_name, b.booking_number, p.plot_number
    FROM `payments` pay
    JOIN `customers` c ON pay.customer_id = c.id
    JOIN `bookings` b ON pay.booking_id = b.id
    JOIN `plots` p ON b.plot_id = p.id
    WHERE pay.society_id = :soc_id AND pay.status = 'posted'
    ORDER BY pay.id DESC
    LIMIT 5
");
$stmt->execute(['soc_id' => $activeSocId]);
$recentPayments = $stmt->fetchAll();

$pageTitle = 'Executive Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Executive Dashboard</h4>
        <p class="text-muted small mb-0">Operational and financial summary for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/bookings/create.php') ?>" class="btn btn-hs-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> New Booking
        </a>
        <a href="<?= base_url('modules/recoveries/create.php') ?>" class="btn btn-success btn-sm">
            <i class="bi bi-cash me-1"></i> Collect Payment
        </a>
    </div>
</div>

<!-- Primary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Total Plot Inventory</div>
                <div class="metric-value"><?= number_format((int)$plotStats['total_plots']) ?></div>
                <div class="small text-muted mt-1">
                    <span class="text-success fw-semibold"><?= (int)$plotStats['available_plots'] ?></span> Available | 
                    <span class="text-primary fw-semibold"><?= (int)$plotStats['booked_plots'] + (int)$plotStats['sold_plots'] ?></span> Sold
                </div>
            </div>
            <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-geo-alt"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Gross Plot Sales</div>
                <div class="metric-value"><?= format_currency($saleStats['total_sale_value']) ?></div>
                <div class="small text-muted mt-1">
                    Booked value in active contracts
                </div>
            </div>
            <div class="metric-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-currency-dollar"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Recovery (This Month)</div>
                <div class="metric-value text-success"><?= format_currency($recoveryStats['month_recovery']) ?></div>
                <div class="small text-muted mt-1">
                    Today: <span class="fw-bold"><?= format_currency($recoveryStats['today_recovery']) ?></span>
                </div>
            </div>
            <div class="metric-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-wallet2"></i>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="metric-card">
            <div>
                <div class="metric-label">Total Overdue Dues</div>
                <div class="metric-value text-danger"><?= format_currency($overdueStats['total_overdue']) ?></div>
                <div class="small text-muted mt-1">
                    <span class="fw-bold text-danger"><?= (int)$overdueStats['overdue_installments_count'] ?></span> Overdue installments
                </div>
            </div>
            <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-exclamation-octagon"></i>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Financial Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-hs h-100">
            <div class="card-header">
                <span><i class="bi bi-pie-chart me-2"></i> Plot Inventory Breakdown</span>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php if ((int)$plotStats['total_plots'] === 0): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        No plots configured yet in this society.<br>
                        <a href="<?= base_url('modules/plots/create.php') ?>" class="btn btn-sm btn-outline-primary mt-2">Add First Plot</a>
                    </div>
                <?php else: ?>
                    <canvas id="inventoryChart" style="max-height: 220px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-hs h-100">
            <div class="card-header">
                <span><i class="bi bi-cash-coin me-2"></i> Financial Receivables & Position</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-muted small">Total Outstanding Balance</div>
                            <div class="fs-5 fw-bold text-dark mt-1"><?= format_currency($saleStats['total_receivable']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-muted small">Customer Advance Liabilities</div>
                            <div class="fs-5 fw-bold text-primary mt-1"><?= format_currency($saleStats['customer_advance']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-muted small">Expenses (This Month)</div>
                            <div class="fs-5 fw-bold text-secondary mt-1"><?= format_currency($monthExpenses) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-muted small">Net Cash Flow (Recovery - Expenses)</div>
                            <?php $netFlow = (float)$recoveryStats['month_recovery'] - $monthExpenses; ?>
                            <div class="fs-5 fw-bold <?= ($netFlow >= 0) ? 'text-success' : 'text-danger' ?> mt-1">
                                <?= format_currency($netFlow) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities Split Tables -->
<div class="row g-3">
    <!-- Recent Bookings -->
    <div class="col-lg-6">
        <div class="card-hs">
            <div class="card-header">
                <span><i class="bi bi-clock-history me-1"></i> Recent Plot Bookings</span>
                <a href="<?= base_url('modules/bookings/index.php') ?>" class="small text-decoration-none">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Customer</th>
                            <th>Plot</th>
                            <th>Sale Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentBookings)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No bookings recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentBookings as $bk): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('modules/bookings/view.php?id=' . (int)$bk['id']) ?>" class="fw-semibold text-decoration-none">
                                            <?= e($bk['booking_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($bk['customer_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($bk['block_name']) ?> - <?= e($bk['plot_number']) ?></span></td>
                                    <td><?= format_currency($bk['net_sale_price']) ?></td>
                                    <td class="text-muted small"><?= format_date($bk['booking_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-6">
        <div class="card-hs">
            <div class="card-header">
                <span><i class="bi bi-receipt-cutoff me-1"></i> Recent Recovery Receipts</span>
                <a href="<?= base_url('modules/receipts/index.php') ?>" class="small text-decoration-none">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hs">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No recovery receipts collected yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('modules/receipts/print.php?id=' . (int)$pay['id']) ?>" target="_blank" class="fw-semibold text-decoration-none">
                                            <?= e($pay['receipt_number']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($pay['customer_name']) ?></td>
                                    <td class="fw-bold text-success"><?= format_currency($pay['payment_amount']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= ucfirst(e($pay['payment_method'])) ?></span></td>
                                    <td class="text-muted small"><?= format_date($pay['payment_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ((int)$plotStats['total_plots'] > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('inventoryChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Reserved', 'Booked', 'Sold', 'Cancelled'],
                datasets: [{
                    data: [
                        <?= (int)$plotStats['available_plots'] ?>,
                        <?= (int)$plotStats['reserved_plots'] ?>,
                        <?= (int)$plotStats['booked_plots'] ?>,
                        <?= (int)$plotStats['sold_plots'] ?>,
                        <?= (int)$plotStats['cancelled_plots'] ?>
                    ],
                    backgroundColor: ['#22c55e', '#f59e0b', '#6366f1', '#3b82f6', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
