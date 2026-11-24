<?php
/**
 * HSMS - Add New Plot to Inventory
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('plots_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
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
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('phase_id', 'Phase')
              ->required('block_id', 'Block')
              ->required('plot_number', 'Plot Number')
              ->required('plot_type_id', 'Plot Type')
              ->required('plot_size_id', 'Plot Size')
              ->minNumber('base_price', 1.0, 'Base Price');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        // Check for duplicate plot number in this block
        $stmt = $db->prepare("SELECT COUNT(*) FROM `plots` WHERE `block_id` = :bid AND `plot_number` = :pnum");
        $stmt->execute(['bid' => $blockId, 'pnum' => $plotNumber]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['plot_number'] = "Plot '{$plotNumber}' already exists in the selected block.";
        } else {
            $totalPrice = ($basePrice + $devCharges + $extraCharges) - $discount;
            if ($totalPrice <= 0) {
                $errors['base_price'] = "Net plot price cannot be zero or negative.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO `plots` (
                        `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`,
                        `plot_number`, `street_road`, `sector_zone`, `is_corner`, `is_boulevard`,
                        `is_park_facing`, `base_price`, `development_charges`, `additional_charges`,
                        `discount_allowed`, `total_price`, `status`, `remarks`
                    ) VALUES (
                        :sid, :pid, :bid, :ptid, :psid,
                        :pnum, :street, :zone, :corner, :blvd,
                        :park, :base, :dev, :extra,
                        :disc, :total, 'available', :remarks
                    )
                ");
                $stmt->execute([
                    'sid'     => $activeSocId,
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
                    'remarks' => $remarks ?: null
                ]);

                $plotId = (int)$db->lastInsertId();
                log_audit('PLOT_CREATED', 'plots', $plotId, null, ['plot_number' => $plotNumber, 'total_price' => $totalPrice], $activeSocId);
                redirect_with_message(base_url('modules/plots/index.php'), "Plot {$plotNumber} added to inventory successfully!", 'success');
            }
        }
    }
}

$pageTitle = 'Add New Plot';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-hs">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-geo-alt-fill me-2"></i> Register New Plot in Inventory</span>
                <a href="<?= base_url('modules/plots/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Inventory</a>
            </div>
            <div class="card-body">
                <form method="POST" id="plotForm">
                    <?= csrf_field() ?>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-pin-map me-1"></i> Location & Hierarchy</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Project Phase <span class="text-danger">*</span></label>
                            <select name="phase_id" id="phase_id" class="form-select form-select-sm <?= isset($errors['phase_id']) ? 'is-invalid' : '' ?>" required onchange="loadBlocks(this.value)">
                                <option value="">Select Phase</option>
                                <?php foreach ($allPhases as $ph): ?>
                                    <option value="<?= (int)$ph['id'] ?>" <?= ((int)($_POST['phase_id'] ?? 0) === (int)$ph['id']) ? 'selected' : '' ?>>
                                        <?= e($ph['phase_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['phase_id'])): ?><div class="invalid-feedback"><?= e($errors['phase_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Block / Sector <span class="text-danger">*</span></label>
                            <select name="block_id" id="block_id" class="form-select form-select-sm <?= isset($errors['block_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Block</option>
                            </select>
                            <?php if (isset($errors['block_id'])): ?><div class="invalid-feedback"><?= e($errors['block_id']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Plot Number <span class="text-danger">*</span></label>
                            <input type="text" name="plot_number" class="form-control form-control-sm <?= isset($errors['plot_number']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. 101 or 12-B" required value="<?= e($_POST['plot_number'] ?? '') ?>">
                            <?php if (isset($errors['plot_number'])): ?><div class="invalid-feedback"><?= e($errors['plot_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plot Type <span class="text-danger">*</span></label>
                            <select name="plot_type_id" class="form-select form-select-sm" required>
                                <?php foreach ($plotTypes as $pt): ?>
                                    <option value="<?= (int)$pt['id'] ?>" <?= ((int)($_POST['plot_type_id'] ?? 0) === (int)$pt['id']) ? 'selected' : '' ?>>
                                        <?= e($pt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Plot Size <span class="text-danger">*</span></label>
                            <select name="plot_size_id" class="form-select form-select-sm" required>
                                <?php foreach ($plotSizes as $ps): ?>
                                    <option value="<?= (int)$ps['id'] ?>" <?= ((int)($_POST['plot_size_id'] ?? 0) === (int)$ps['id']) ? 'selected' : '' ?>>
                                        <?= e($ps['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Street / Road Name</label>
                            <input type="text" name="street_road" class="form-control form-control-sm" placeholder="e.g. Street 14, 40-ft Road" value="<?= e($_POST['street_road'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sector / Zone</label>
                            <input type="text" name="sector_zone" class="form-control form-control-sm" placeholder="e.g. Sector B-1" value="<?= e($_POST['sector_zone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold d-block mb-2">Prime Features</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_corner" id="is_corner" value="1" <?= isset($_POST['is_corner']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_corner">Corner Plot</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_boulevard" id="is_boulevard" value="1" <?= isset($_POST['is_boulevard']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_boulevard">Main Boulevard</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_park_facing" id="is_park_facing" value="1" <?= isset($_POST['is_park_facing']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_park_facing">Park Facing</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-cash-coin me-1"></i> Pricing & Cost Structure</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Base Price (PKR) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="base_price" id="base_price" class="form-control form-control-sm price-input" 
                                   required value="<?= e($_POST['base_price'] ?? '0.00') ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Development Charges</label>
                            <input type="number" step="0.01" name="development_charges" id="development_charges" class="form-control form-control-sm price-input" 
                                   value="<?= e($_POST['development_charges'] ?? '0.00') ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Additional Prime Charges</label>
                            <input type="number" step="0.01" name="additional_charges" id="additional_charges" class="form-control form-control-sm price-input" 
                                   value="<?= e($_POST['additional_charges'] ?? '0.00') ?>" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Allowed Discount</label>
                            <input type="number" step="0.01" name="discount_allowed" id="discount_allowed" class="form-control form-control-sm price-input" 
                                   value="<?= e($_POST['discount_allowed'] ?? '0.00') ?>" oninput="calculateTotal()">
                        </div>
                    </div>

                    <div class="p-3 mb-4 rounded bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary fs-6">Net Total Price Calculation:</span>
                        <span class="fs-4 fw-bold text-primary" id="net_total_display">Rs. 0.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks / Description</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"><?= e($_POST['remarks'] ?? '') ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Register Plot
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
    } else {
        blockSelect.innerHTML = '<option value="">No active blocks found</option>';
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
    const initialPhase = document.getElementById('phase_id').value;
    if (initialPhase) {
        loadBlocks(initialPhase, <?= (int)($_POST['block_id'] ?? 0) ?>);
    }
    calculateTotal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
