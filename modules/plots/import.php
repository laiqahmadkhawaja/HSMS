<?php
/**
 * HSMS - Bulk Plot Inventory CSV Import
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('plots_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

// Handle Sample Template Download
if (isset($_GET['download_sample']) && $_GET['download_sample'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hsms_plot_import_template.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Phase_Name', 'Block_Name', 'Plot_Number', 'Plot_Type', 'Plot_Size',
        'Street_Road', 'Is_Corner', 'Is_Boulevard', 'Is_Park_Facing',
        'Base_Price', 'Development_Charges', 'Additional_Charges', 'Remarks'
    ]);
    fputcsv($output, [
        'Phase 1 - Executive', 'Block A - Rose', '101', 'Residential', '5 Marla',
        'Street 4', '0', '0', '0',
        '2500000', '400000', '0', 'Standard inventory plot'
    ]);
    fputcsv($output, [
        'Phase 1 - Executive', 'Block A - Rose', '102', 'Residential', '10 Marla',
        'Main Boulevard', '1', '1', '0',
        '4500000', '700000', '500000', 'Corner Boulevard plot'
    ]);
    fclose($output);
    exit;
}

$importErrors = [];
$successCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please select a valid CSV file to upload.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $importErrors[] = 'Only standard .csv files are supported.';
        } else {
            $handle = fopen($file, 'r');
            if ($handle === false) {
                $importErrors[] = 'Unable to read the uploaded CSV file.';
            } else {
                // Read header row
                $header = fgetcsv($handle);
                $rowNum = 1;

                // Cache reference lookups to avoid repetitive queries
                $phasesCache = [];
                $blocksCache = [];
                $typesCache = [];
                $sizesCache = [];

                $rowsToInsert = [];

                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    // Skip completely empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    if (count($row) < 10) {
                        $importErrors[] = "Row {$rowNum}: Insufficient columns. Minimum 10 columns required.";
                        continue;
                    }

                    $phaseName = trim($row[0]);
                    $blockName = trim($row[1]);
                    $plotNum = strtoupper(trim($row[2]));
                    $plotType = trim($row[3]);
                    $plotSize = trim($row[4]);
                    $street = trim($row[5] ?? '');
                    $isCorner = (int)($row[6] ?? 0);
                    $isBlvd = (int)($row[7] ?? 0);
                    $isPark = (int)($row[8] ?? 0);
                    $basePrice = (float)($row[9] ?? 0);
                    $devCharges = (float)($row[10] ?? 0);
                    $extraCharges = (float)($row[11] ?? 0);
                    $remarks = trim($row[12] ?? '');

                    // Validate Phase
                    if (!isset($phasesCache[$phaseName])) {
                        $s = $db->prepare("SELECT id FROM `phases` WHERE `society_id` = :sid AND `phase_name` = :name");
                        $s->execute(['sid' => $activeSocId, 'name' => $phaseName]);
                        $phasesCache[$phaseName] = (int)$s->fetchColumn();
                    }
                    $phaseId = $phasesCache[$phaseName];
                    if ($phaseId <= 0) {
                        $importErrors[] = "Row {$rowNum}: Phase '{$phaseName}' does not exist in active society.";
                        continue;
                    }

                    // Validate Block
                    $blockKey = "{$phaseId}_{$blockName}";
                    if (!isset($blocksCache[$blockKey])) {
                        $s = $db->prepare("SELECT id FROM `blocks` WHERE `phase_id` = :pid AND `block_name` = :name");
                        $s->execute(['pid' => $phaseId, 'name' => $blockName]);
                        $blocksCache[$blockKey] = (int)$s->fetchColumn();
                    }
                    $blockId = $blocksCache[$blockKey];
                    if ($blockId <= 0) {
                        $importErrors[] = "Row {$rowNum}: Block '{$blockName}' does not exist under phase '{$phaseName}'.";
                        continue;
                    }

                    // Validate Plot Type
                    if (!isset($typesCache[$plotType])) {
                        $s = $db->prepare("SELECT id FROM `plot_types` WHERE `name` = :name");
                        $s->execute(['name' => $plotType]);
                        $typesCache[$plotType] = (int)$s->fetchColumn();
                    }
                    $typeId = $typesCache[$plotType];
                    if ($typeId <= 0) {
                        $importErrors[] = "Row {$rowNum}: Plot Type '{$plotType}' is invalid.";
                        continue;
                    }

                    // Validate Plot Size
                    if (!isset($sizesCache[$plotSize])) {
                        $s = $db->prepare("SELECT id FROM `plot_sizes` WHERE `name` = :name");
                        $s->execute(['name' => $plotSize]);
                        $sizesCache[$plotSize] = (int)$s->fetchColumn();
                    }
                    $sizeId = $sizesCache[$plotSize];
                    if ($sizeId <= 0) {
                        $importErrors[] = "Row {$rowNum}: Plot Size '{$plotSize}' is invalid.";
                        continue;
                    }

                    if ($plotNum === '') {
                        $importErrors[] = "Row {$rowNum}: Plot Number cannot be blank.";
                        continue;
                    }

                    if ($basePrice <= 0) {
                        $importErrors[] = "Row {$rowNum}: Base Price must be greater than 0.";
                        continue;
                    }

                    // Check for duplicate in block
                    $s = $db->prepare("SELECT COUNT(*) FROM `plots` WHERE `block_id` = :bid AND `plot_number` = :pnum");
                    $s->execute(['bid' => $blockId, 'pnum' => $plotNum]);
                    if ((int)$s->fetchColumn() > 0) {
                        $importErrors[] = "Row {$rowNum}: Plot '{$plotNum}' already exists in {$blockName}.";
                        continue;
                    }

                    $totalPrice = $basePrice + $devCharges + $extraCharges;

                    $rowsToInsert[] = [
                        'sid'     => $activeSocId,
                        'pid'     => $phaseId,
                        'bid'     => $blockId,
                        'ptid'    => $typeId,
                        'psid'    => $sizeId,
                        'pnum'    => $plotNum,
                        'street'  => $street ?: null,
                        'corner'  => $isCorner,
                        'blvd'    => $isBlvd,
                        'park'    => $isPark,
                        'base'    => $basePrice,
                        'dev'     => $devCharges,
                        'extra'   => $extraCharges,
                        'total'   => $totalPrice,
                        'remarks' => $remarks ?: 'Imported via CSV'
                    ];
                }
                fclose($handle);

                // If no critical errors, execute batch insert inside transaction
                if (empty($importErrors) && !empty($rowsToInsert)) {
                    $db->beginTransaction();
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO `plots` (
                                `society_id`, `phase_id`, `block_id`, `plot_type_id`, `plot_size_id`,
                                `plot_number`, `street_road`, `is_corner`, `is_boulevard`, `is_park_facing`,
                                `base_price`, `development_charges`, `additional_charges`, `total_price`,
                                `status`, `remarks`
                            ) VALUES (
                                :sid, :pid, :bid, :ptid, :psid,
                                :pnum, :street, :corner, :blvd, :park,
                                :base, :dev, :extra, :total,
                                'available', :remarks
                            )
                        ");

                        foreach ($rowsToInsert as $r) {
                            $stmt->execute($r);
                            $successCount++;
                        }

                        $db->commit();
                        log_audit('PLOTS_BULK_IMPORTED', 'plots', null, null, ['count' => $successCount], $activeSocId);
                        redirect_with_message(base_url('modules/plots/index.php'), "Successfully imported {$successCount} plots into inventory!", 'success');
                    } catch (Throwable $e) {
                        $db->rollBack();
                        $importErrors[] = 'Database transaction failed during bulk insert: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$pageTitle = 'Bulk Plot Inventory Import';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-file-earmark-arrow-up me-2"></i> Bulk Plot Inventory Import (CSV)</span>
                <a href="<?= base_url('modules/plots/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Inventory</a>
            </div>
            <div class="card-body">
                <div class="p-3 mb-4 rounded bg-light border">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle me-1"></i> Import Instructions & Rules</h6>
                    <ul class="small text-muted mb-3 ps-3">
                        <li>Phase and Block names in CSV must match the active society's existing phases and blocks.</li>
                        <li>Plot sizes (e.g. <code>5 Marla</code>, <code>10 Marla</code>, <code>1 Kanal</code>) and Plot types (<code>Residential</code>, <code>Commercial</code>) must exist in the system.</li>
                        <li>Is_Corner, Is_Boulevard, Is_Park_Facing should be <code>1</code> for Yes, <code>0</code> for No.</li>
                        <li>Prices should be plain numbers (e.g. <code>2500000</code>). Do not include commas or currency symbols.</li>
                    </ul>
                    <a href="<?= base_url('modules/plots/import.php?download_sample=1') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i> Download Sample CSV Template
                    </a>
                </div>

                <?php if (!empty($importErrors)): ?>
                    <div class="alert alert-danger py-2 small mb-4">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> CSV Validation Failed:</div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($importErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Select CSV File to Upload <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Validate & Import Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
