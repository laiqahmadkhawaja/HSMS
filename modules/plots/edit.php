<?php
/**
 * HSMS - Edit Plot Specifications & Pricing
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('plots_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT p.*, ph.phase_name, b.block_name 
    FROM `plots` p
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `blocks` b ON p.block_id = b.id
    WHERE p.id = :id AND p.society_id = :sid
    LIMIT 1
");
$stmt->execute(['id' => $id, 'sid' => $activeSocId]);
$plot = $stmt->fetch();

if (!$plot) {
    redirect_with_message(base_url('modules/plots/index.php'), 'Plot not found.', 'danger');
}

$errors = [];

// Fetch Master Options
$phases = $db->prepare("SELECT id, phase_name FROM `phases` WHERE `society_id` = :sid AND `status` = 'active' ORDER BY `phase_name` ASC");
$phases->execute(['sid' => $activeSocId]);
$allPhases = $phases->fetchAll();

$plotTypes = $db->query("SELECT id, name FROM `plot_types` WHERE `status` = 'active' ORDER BY `id` ASC")->fetchAll();
$plotSizes = $db->query("SELECT id, name, unit FROM `plot_sizes` WHERE `status` = 'active' ORDER BY `size_value` ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $phaseId = (int)($_POST['phase_id'] ?? 0);
    $blockId = (int)($_POST['block_id'] ?? 0);
    $plotNumber = strtoupper(trim($_POST['plot_number'] ?? ''));
    $plotTypeId = (int)($_POST['plot_type_id'] ?? 0);
    $plotSizeId = (int)($_POST['plot_size_id'] ?? 0);
    $streetRoad = trim($_POST['street_road'] ?? '');
    $sectorZone = trim($_POST['sector_zone'] ?? '');
    $isCorner = isset($_POST['is_corner']) ? 1 : 0;
    $isBoulevard = isset($_POST['is_boulevard']) ? 1 : 0;
    $isParkFacing = isset($_POST['is_park_facing']) ? 1 : 0;

    $basePrice = (float)($_POST['base_price'] ?? 0);
    $devCharges = (float)($_POST['development_charges'] ?? 0);
    $extraCharges = (float)($_POST['additional_charges'] ?? 0);
    $discount = (float)($_POST['discount_allowed'] ?? 0);
    $priceReason = trim($_POST['price_change_reason'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['available', 'reserved', 'blocked', 'cancelled']) ? $_POST['status'] : $plot['status'];
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('phase_id', 'Phase')
              ->required('block_id', 'Block')
              ->required('plot_number', 'Plot Number')
              ->minNumber('base_price', 1.0, 'Base Price');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Check duplicate plot number in block (excluding this plot ID)
        $stmt = $db->prepare("SELECT COUNT(*) FROM `plots` WHERE `block_id` = :bid AND `plot_number` = :pnum AND `id` != :id");
        $stmt->execute(['bid' => $blockId, 'pnum' => $plotNumber, 'id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['plot_number'] = "Plot '{$plotNumber}' already exists in the selected block.";
        } else {
            $totalPrice = ($basePrice + $devCharges + $extraCharges) - $discount;

            $db->beginTransaction();
            try {
                // If base price changed, record in plot_price_history
                if (abs((float)$plot['base_price'] - $basePrice) > 0.01) {
                    $user = current_user();
                    $stmtHist = $db->prepare("
                        INSERT INTO `plot_price_history` (`plot_id`, `old_base_price`, `new_base_price`, `reason`, `changed_by`)
                        VALUES (:pid, :old_p, :new_p, :reason, :uid)
                    ");
                    $stmtHist->execute([
                        'pid'    => $id,
                        'old_p'  => $plot['base_price'],
                        'new_p'  => $basePrice,
                        'reason' => $priceReason ?: 'Price adjustment via edit screen',
                        'uid'    => $user['id'] ?? 1
                    ]);
                }

                $stmt = $db->prepare("
                    UPDATE `plots` SET
                        `phase_id` = :pid,
                        `block_id` = :bid,
                        `plot_type_id` = :ptid,
                        `plot_size_id` = :psid,
                        `plot_number` = :pnum,
                        `street_road` = :street,
                        `sector_zone` = :zone,
                        `is_corner` = :corner,
                        `is_boulevard` = :blvd,
                        `is_park_facing` = :park,
                        `base_price` = :base,
                        `development_charges` = :dev,
                        `additional_charges` = :extra,
                        `discount_allowed` = :disc,
                        `total_price` = :total,
                        `status` = :status,
                        `remarks` = :remarks
                    WHERE `id` = :id AND `society_id` = :sid
                ");
                $stmt->execute([
                    'pid'     => $phaseId,
                    'bid'     => $blockId,
                    'ptid'    => $plotTypeId,
                    'psid'    => $plotSizeId,
                    'pnum'    => $plotNumber,
                    'street'  => $streetRoad ?: null,
                    'zone'    => $sectorZone ?: null,
                    'corner'  => $isCorner,
                    'blvd'    => $isBoulevard,
                    'park'    => $isParkFacing,
                    'base'    => $basePrice,
                    'dev'     => $devCharges,
                    'extra'   => $extraCharges,
                    'disc'    => $discount,
                    'total'   => $totalPrice,
                    'status'  => $status,
                    'remarks' => $remarks ?: null,
                    'id'      => $id,
                    'sid'     => $activeSocId
                ]);

                $db->commit();
                log_audit('PLOT_UPDATED', 'plots', $id, $plot, ['plot_number' => $plotNumber, 'total_price' => $totalPrice], $activeSocId);
                redirect_with_message(base_url('modules/plots/view.php?id=' . $id), "Plot {$plotNumber} updated successfully!", 'success');
            } catch (Throwable $e) {
                $db->rollBack();
                $errors['general'] = 'Failed to update plot: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit Plot: ' . $plot['plot_number'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-hs">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Plot Specifications & Pricing</span>
                <a href="<?= base_url('modules/plots/view.php?id=' . $id) ?>" class="btn btn-sm btn-outline-secondary">View Plot</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-pin-map me-1"></i> Location & Hierarchy</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Project Phase <span class="text-danger">*</span></label>
                            <select name="phase_id" id="phase_id" class="form-select form-select-sm" required onchange="loadBlocks(this.value)">
                                <?php foreach ($allPhases as $ph): ?>
                                    <option value="<?= (int)$ph['id'] ?>" <?= ((int)($plot['phase_id']) === (int)$ph['id']) ? 'selected' : '' ?>>
                                        <?= e($ph['phase_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Block / Sector <span class="text-danger">*</span></label>
                            <select name="block_id" id="block_id" class="form-select form-select-sm" required>
                                <option value="<?= (int)$plot['block_id'] ?>" selected><?= e($plot['block_name']) ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Plot Number <span class="text-danger">*</span></label>
                            <input type="text" name="plot_number" class="form-control form-control-sm <?= isset($errors['plot_number']) ? 'is-invalid' : '' ?>" 
                                   required value="<?= e($_POST['plot_number'] ?? $plot['plot_number']) ?>">
                            <?php if (isset($errors['plot_number'])): ?><div class="invalid-feedback"><?= e($errors['plot_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plot Type <span class="text-danger">*</span></label>
                            <select name="plot_type_id" class="form-select form-select-sm" required>
                                <?php foreach ($plotTypes as $pt): ?>
                                    <option value="<?= (int)$pt['id'] ?>" <?= ((int)($plot['plot_type_id']) === (int)$pt['id']) ? 'selected' : '' ?>>
                                        <?= e($pt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plot Size <span class="text-danger">*</span></label>
                            <select name="plot_size_id" class="form-select form-select-sm" required>
                                <?php foreach ($plotSizes as $ps): ?>
                                    <option value="<?= (int)$ps['id'] ?>" <?= ((int)($plot['plot_size_id']) === (int)$ps['id']) ? 'selected' : '' ?>>
                                        <?= e($ps['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Street / Road Name</label>
                            <input type="text" name="street_road" class="form-control form-control-sm" value="<?= e($_POST['street_road'] ?? $plot['street_road']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sector / Zone</label>
                            <input type="text" name="sector_zone" class="form-control form-control-sm" value="<?= e($_POST['sector_zone'] ?? $plot['sector_zone']) ?>">
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold d-block mb-2">Prime Features</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_corner" id="is_corner" value="1" <?= ($plot['is_corner'] ? 'checked' : '') ?>>
                                <label class="form-check-label" for="is_corner">Corner Plot</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_boulevard" id="is_boulevard" value="1" <?= ($plot['is_boulevard'] ? 'checked' : '') ?>>
                                <label class="form-check-label" for="is_boulevard">Main Boulevard</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_park_facing" id="is_park_facing" value="1" <?= ($plot['is_park_facing'] ? 'checked' : '') ?>>
                                <label class="form-check-label" for="is_park_facing">Park Facing</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-cash-coin me-1"></i> Pricing & Cost Structure</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Base Price (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="base_price" id="base_price" class="form-control form-control-sm" 
                                   required value="<?= e($_POST['base_price'] ?? $plot['base_price']) ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Development Charges</label>
                            <input type="number" step="0.01" name="development_charges" id="development_charges" class="form-control form-control-sm" 
                                   value="<?= e($_POST['development_charges'] ?? $plot['development_charges']) ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Additional Prime Charges</label>
                            <input type="number" step="0.01" name="additional_charges" id="additional_charges" class="form-control form-control-sm" 
                                   value="<?= e($_POST['additional_charges'] ?? $plot['additional_charges']) ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Allowed Discount</label>
                            <input type="number" step="0.01" name="discount_allowed" id="discount_allowed" class="form-control form-control-sm" 
                                   value="<?= e($_POST['discount_allowed'] ?? $plot['discount_allowed']) ?>" oninput="calculateTotal()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Price Modification Justification / Reason</label>
                        <input type="text" name="price_change_reason" class="form-control form-control-sm" placeholder="e.g. Board approved annual tariff revision">
                    </div>

                    <div class="p-3 mb-4 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary fs-6">Calculated Net Plot Price:</span>
                        <span class="fs-4 fw-bold text-primary" id="net_total_display"><?= format_currency($plot['total_price']) ?></span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Inventory Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="available" <?= ($plot['status'] === 'available') ? 'selected' : '' ?>>Available</option>
                                <option value="reserved" <?= ($plot['status'] === 'reserved') ? 'selected' : '' ?>>Reserved</option>
                                <option value="blocked" <?= ($plot['status'] === 'blocked') ? 'selected' : '' ?>>Blocked / Retained</option>
                                <?php if ($plot['status'] === 'booked' || $plot['status'] === 'sold'): ?>
                                    <option value="<?= e($plot['status']) ?>" selected disabled><?= ucfirst(e($plot['status'])) ?> (Controlled by sales workflow)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Internal Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($_POST['remarks'] ?? $plot['remarks']) ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Plot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function loadBlocks(phaseId, selectedBlockId = 0) {
    const blockSelect = document.getElementById('block_id');
    blockSelect.innerHTML = '<option value="">Loading blocks...</option>';
    if (!phaseId) {
        blockSelect.innerHTML = '<option value="">Select Phase First</option>';
        return;
    }
    const res = await hsmsFetch('<?= base_url("ajax/plots/get_blocks.php?phase_id=") ?>' + phaseId);
    if (res.success && res.data) {
        let html = '<option value="">Select Block</option>';
        res.data.forEach(blk => {
            const sel = (parseInt(blk.id) === parseInt(selectedBlockId)) ? 'selected' : '';
            html += `<option value="${blk.id}" ${sel}>${blk.block_name} (${blk.block_code})</option>`;
        });
        blockSelect.innerHTML = html;
    }
}

function calculateTotal() {
    const base = parseFloat(document.getElementById('base_price').value) || 0;
    const dev = parseFloat(document.getElementById('development_charges').value) || 0;
    const extra = parseFloat(document.getElementById('additional_charges').value) || 0;
    const disc = parseFloat(document.getElementById('discount_allowed').value) || 0;
    const total = (base + dev + extra) - disc;
    document.getElementById('net_total_display').textContent = 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    loadBlocks(<?= (int)$plot['phase_id'] ?>, <?= (int)$plot['block_id'] ?>);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
