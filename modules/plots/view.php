<?php
/**
 * HSMS - View Plot Profile & History
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('plots_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT p.*, ph.phase_name, b.block_name, pt.name as type_name, ps.name as size_name, ps.dimension_details
    FROM `plots` p
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    WHERE p.id = :id AND p.society_id = :sid
    LIMIT 1
");
$stmt->execute(['id' => $id, 'sid' => $activeSocId]);
$plot = $stmt->fetch();

if (!$plot) {
    redirect_with_message(base_url('modules/plots/index.php'), 'Plot not found.', 'danger');
}

// Fetch Price History
$stmtPrice = $db->prepare("
    SELECT pph.*, u.name as user_name
    FROM `plot_price_history` pph
    JOIN `users` u ON pph.changed_by = u.id
    WHERE pph.plot_id = :pid
    ORDER BY pph.id DESC
");
$stmtPrice->execute(['pid' => $id]);
$priceHistory = $stmtPrice->fetchAll();

// Fetch Active Booking (if any)
$stmtBk = $db->prepare("
    SELECT bk.*, c.full_name as customer_name, c.cnic_passport, c.mobile
    FROM `bookings` bk
    JOIN `customers` c ON bk.customer_id = c.id
    WHERE bk.plot_id = :pid AND bk.status = 'active'
    LIMIT 1
");
$stmtBk->execute(['pid' => $id]);
$activeBooking = $stmtBk->fetch();

$pageTitle = 'Plot Details: ' . $plot['plot_number'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Plot <?= e($plot['plot_number']) ?> — <span class="text-primary"><?= e($plot['block_name']) ?></span></h4>
        <p class="text-muted small mb-0"><?= e($plot['phase_name']) ?>, <?= e($society['name'] ?? 'Society') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/plots/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
        <a href="<?= base_url('modules/plots/edit.php?id=' . $id) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Specifications
        </a>
        <?php if ($plot['status'] === 'available'): ?>
            <a href="<?= base_url('modules/bookings/create.php?plot_id=' . $id) ?>" class="btn btn-hs-primary btn-sm">
                <i class="bi bi-cart-plus me-1"></i> Book Plot
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Specifications Card -->
    <div class="col-lg-7">
        <div class="card-hs h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-1"></i> Physical Specifications</span>
                <span class="status-badge badge-<?= e($plot['status']) ?>"><?= ucfirst(e($plot['status'])) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Plot Number</div>
                        <div class="fw-bold fs-5 text-dark"><?= e($plot['plot_number']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Phase & Block</div>
                        <div class="fw-semibold"><?= e($plot['phase_name']) ?> / <?= e($plot['block_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Plot Category / Type</div>
                        <div class="fw-semibold"><?= e($plot['type_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Plot Size & Dimensions</div>
                        <div class="fw-semibold"><?= e($plot['size_name']) ?> (<?= e($plot['dimension_details'] ?? 'Standard') ?>)</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Street / Location</div>
                        <div><?= e($plot['street_road'] ?: 'Standard Street') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Prime Features</div>
                        <div>
                            <?php if ($plot['is_corner']): ?><span class="badge bg-info text-dark me-1">Corner</span><?php endif; ?>
                            <?php if ($plot['is_boulevard']): ?><span class="badge bg-primary text-white me-1">Main Boulevard</span><?php endif; ?>
                            <?php if ($plot['is_park_facing']): ?><span class="badge bg-success text-white me-1">Park Facing</span><?php endif; ?>
                            <?php if (!$plot['is_corner'] && !$plot['is_boulevard'] && !$plot['is_park_facing']): ?><span class="text-muted small">None</span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Breakdown Card -->
    <div class="col-lg-5">
        <div class="card-hs h-100">
            <div class="card-header">
                <span><i class="bi bi-cash-stack me-1"></i> Cost & Price Breakdown</span>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Base Plot Price:</td>
                        <td class="text-end fw-semibold"><?= format_currency($plot['base_price']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Development Charges:</td>
                        <td class="text-end"><?= format_currency($plot['development_charges']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Additional / Prime Charges:</td>
                        <td class="text-end"><?= format_currency($plot['additional_charges']) ?></td>
                    </tr>
                    <?php if ((float)$plot['discount_allowed'] > 0): ?>
                        <tr class="text-success">
                            <td>Approved Discount:</td>
                            <td class="text-end">- <?= format_currency($plot['discount_allowed']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td class="fw-bold fs-6 text-dark pt-2">Total Net Sale Price:</td>
                        <td class="text-end fw-bold fs-6 text-primary pt-2"><?= format_currency($plot['total_price']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Active Booking & Ownership Details -->
<?php if ($activeBooking): ?>
    <div class="card-hs mb-4 border-primary">
        <div class="card-header bg-primary bg-opacity-10 text-primary fw-bold">
            <i class="bi bi-person-check me-1"></i> Current Ownership & Active Booking
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-3">
                    <div class="text-muted small">Booking Number</div>
                    <div class="fw-bold"><a href="<?= base_url('modules/bookings/view.php?id=' . (int)$activeBooking['id']) ?>" class="text-decoration-none"><?= e($activeBooking['booking_number']) ?></a></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">Buyer / Customer</div>
                    <div class="fw-semibold"><?= e($activeBooking['customer_name']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">CNIC & Phone</div>
                    <div><?= e($activeBooking['cnic_passport']) ?> / <?= e($activeBooking['mobile']) ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">Balance Outstanding</div>
                    <div class="fw-bold text-danger"><?= format_currency($activeBooking['remaining_balance']) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Price Revision History -->
<div class="card-hs">
    <div class="card-header">
        <i class="bi bi-clock-history me-1"></i> Price Modification History
    </div>
    <div class="table-responsive">
        <table class="table table-hs">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Previous Base Price</th>
                    <th>Revised Base Price</th>
                    <th>Adjustment Reason</th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($priceHistory)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No historical price changes recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($priceHistory as $ph): ?>
                        <tr>
                            <td><?= format_date($ph['changed_at'], 'd M Y, h:i A') ?></td>
                            <td><?= format_currency($ph['old_base_price']) ?></td>
                            <td class="fw-bold text-primary"><?= format_currency($ph['new_base_price']) ?></td>
                            <td><?= e($ph['reason']) ?></td>
                            <td><?= e($ph['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
