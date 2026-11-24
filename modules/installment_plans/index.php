<?php
/**
 * HSMS - Installment Plan Templates Listing
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('masters_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$stmt = $db->prepare("
    SELECT ipt.*,
        (SELECT COUNT(*) FROM `bookings` WHERE `plan_template_id` = ipt.id) as bookings_count
    FROM `installment_plan_templates` ipt
    WHERE (ipt.society_id = :sid OR ipt.society_id IS NULL)
    ORDER BY ipt.id ASC
");
$stmt->execute(['sid' => $activeSocId]);
$plans = $stmt->fetchAll();

$pageTitle = 'Installment Plan Templates';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Installment Plan Templates</h4>
        <p class="text-muted small mb-0">Pre-configured payment options for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
    <a href="<?= base_url('modules/installment_plans/create.php') ?>" class="btn btn-hs-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Add New Plan Template
    </a>
</div>

<div class="card-hs">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hs">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Plan Name</th>
                        <th>Duration</th>
                        <th>Down Payment</th>
                        <th>Frequency</th>
                        <th>Installments</th>
                        <th>Grace Period</th>
                        <th>Bookings Using</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No payment plan templates configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($plans as $p): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($p['plan_code']) ?></span></td>
                                <td class="fw-bold">
                                    <a href="<?= base_url('modules/installment_plans/edit.php?id=' . (int)$p['id']) ?>" class="text-decoration-none">
                                        <?= e($p['plan_name']) ?>
                                    </a>
                                </td>
                                <td><?= (int)$p['duration_months'] ?> Months</td>
                                <td>
                                    <?php if ($p['down_payment_type'] === 'percentage'): ?>
                                        <span class="fw-bold text-primary"><?= (float)$p['down_payment_value'] ?>%</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-primary"><?= format_currency($p['down_payment_value']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= ucfirst(e($p['frequency'])) ?></span></td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary fw-bold"><?= (int)$p['total_installments'] ?></span></td>
                                <td><?= (int)$p['grace_period_days'] ?> Days</td>
                                <td><span class="badge bg-info bg-opacity-10 text-info fw-bold"><?= (int)$p['bookings_count'] ?></span></td>
                                <td>
                                    <span class="badge <?= ($p['status'] === 'active') ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst(e($p['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('modules/installment_plans/edit.php?id=' . (int)$p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit Plan">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
