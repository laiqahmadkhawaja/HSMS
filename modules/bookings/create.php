<?php
/**
 * HSMS - Create New Plot Booking
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/booking_service.php';
require_permission('bookings_create');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();
$preSelectedPlotId = (int)($_GET['plot_id'] ?? 0);
$preSelectedCustId = (int)($_GET['customer_id'] ?? 0);
$errors = [];

// Fetch available plots
$stmtPlots = $db->prepare("
    SELECT p.id, p.plot_number, p.base_price, p.development_charges, p.additional_charges, p.discount_allowed, p.total_price,
           b.block_name, ph.phase_name, ps.name as size_name, pt.name as type_name
    FROM `plots` p
    JOIN `blocks` b ON p.block_id = b.id
    JOIN `phases` ph ON p.phase_id = ph.id
    JOIN `plot_sizes` ps ON p.plot_size_id = ps.id
    JOIN `plot_types` pt ON p.plot_type_id = pt.id
    WHERE p.society_id = :sid AND (p.status = 'available' OR p.id = :pre_pid)
    ORDER BY b.block_name ASC, p.plot_number ASC
");
$stmtPlots->execute(['sid' => $activeSocId, 'pre_pid' => $preSelectedPlotId]);
$availablePlots = $stmtPlots->fetchAll();

// Fetch active customers
$stmtCusts = $db->query("SELECT id, customer_id_code, full_name, cnic_passport, mobile FROM `customers` WHERE `status` = 'active' ORDER BY `full_name` ASC");
$customers = $stmtCusts->fetchAll();

// Fetch plan templates
$stmtPlans = $db->prepare("SELECT * FROM `installment_plan_templates` WHERE (society_id = :sid OR society_id IS NULL) AND `status` = 'active' ORDER BY `id` ASC");
$stmtPlans->execute(['sid' => $activeSocId]);
$planTemplates = $stmtPlans->fetchAll();

// Fetch sales officers
$stmtOfficers = $db->query("SELECT id, name FROM `users` WHERE `status` = 'active' ORDER BY `name` ASC");
$officers = $stmtOfficers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $plotId = (int)($_POST['plot_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $templateId = (int)($_POST['plan_template_id'] ?? 0) ?: null;
    $bookingDate = trim($_POST['booking_date'] ?? date('Y-m-d'));

    $basePrice = (float)($_POST['base_price'] ?? 0);
    $devCharges = (float)($_POST['development_charges'] ?? 0);
    $extraCharges = (float)($_POST['extra_charges'] ?? 0);
    $discount = (float)($_POST['discount_amount'] ?? 0);
    $downPayment = (float)($_POST['down_payment_amount'] ?? 0);

    $installmentsCount = (int)($_POST['total_installments'] ?? 1);
    $frequency = (string)($_POST['frequency'] ?? 'monthly');
    $dueDay = (int)($_POST['due_day'] ?? 10);
    $salesOfficerId = (int)($_POST['sales_officer_id'] ?? 0) ?: null;
    $remarks = trim($_POST['remarks'] ?? '');

    $validator = new Validator($_POST);
    $validator->required('plot_id', 'Plot Selection')
              ->required('customer_id', 'Customer Selection')
              ->required('booking_date', 'Booking Date')
              ->date('booking_date', 'Booking Date')
              ->minNumber('base_price', 1.0, 'Base Price')
              ->minNumber('total_installments', 1, 'Total Installments');

    if ($validator->fails()) {
        $errors = $validator->getErrors();
    } else {
        try {
            $user = current_user();
            $newBookingId = BookingService::createBooking(
                $activeSocId,
                $plotId,
                $customerId,
                $templateId,
                $bookingDate,
                $basePrice,
                $devCharges,
                $extraCharges,
                $discount,
                $downPayment,
                $installmentsCount,
                $frequency,
                $dueDay,
                $salesOfficerId,
                $user['id'] ?? 1,
                $remarks
            );

            redirect_with_message(base_url('modules/bookings/view.php?id=' . $newBookingId), "Plot booked successfully!", 'success');
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

$pageTitle = 'Create New Plot Booking';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="card-hs">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-cart-plus-fill me-2"></i> Plot Booking & Sales Contract</span>
                <a href="<?= base_url('modules/bookings/index.php') ?>" class="btn btn-sm btn-outline-secondary">Back to Bookings</a>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="bookingForm">
                    <?= csrf_field() ?>

                    <!-- Row 1: Customer & Plot Selection -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Buyer / Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select form-select-sm <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= (($preSelectedCustId === (int)$c['id']) || ((int)($_POST['customer_id'] ?? 0) === (int)$c['id'])) ? 'selected' : '' ?>>
                                        <?= e($c['full_name']) ?> (<?= e($c['customer_id_code']) ?> — CNIC: <?= e($c['cnic_passport']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['customer_id'])): ?><div class="invalid-feedback"><?= e($errors['customer_id']) ?></div><?php endif; ?>
                            <div class="form-text small">Don't see the buyer? <a href="<?= base_url('modules/customers/create.php') ?>" target="_blank">Register New Customer</a></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Available Plot <span class="text-danger">*</span></label>
                            <select name="plot_id" id="plot_id" class="form-select form-select-sm <?= isset($errors['plot_id']) ? 'is-invalid' : '' ?>" required onchange="handlePlotChange(this.value)">
                                <option value="">Choose Plot</option>
                                <?php foreach ($availablePlots as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" <?= (($preSelectedPlotId === (int)$p['id']) || ((int)($_POST['plot_id'] ?? 0) === (int)$p['id'])) ? 'selected' : '' ?>>
                                        Plot #<?= e($p['plot_number']) ?> — <?= e($p['block_name']) ?> (<?= e($p['size_name']) ?> <?= e($p['type_name']) ?>) - Rs. <?= number_format((float)$p['total_price'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['plot_id'])): ?><div class="invalid-feedback"><?= e($errors['plot_id']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <!-- Row 2: Pricing Structure & Calculations -->
                    <div class="p-3 mb-4 rounded bg-light border">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calculator me-1"></i> Pricing Breakdown & Discount</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Base Plot Price</label>
                                <input type="number" step="0.01" name="base_price" id="base_price" class="form-control form-control-sm" required value="<?= e($_POST['base_price'] ?? '0.00') ?>" oninput="recalcNetAndSchedule()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Development Charges</label>
                                <input type="number" step="0.01" name="development_charges" id="development_charges" class="form-control form-control-sm" value="<?= e($_POST['development_charges'] ?? '0.00') ?>" oninput="recalcNetAndSchedule()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Prime Extra Charges</label>
                                <input type="number" step="0.01" name="extra_charges" id="extra_charges" class="form-control form-control-sm" value="<?= e($_POST['extra_charges'] ?? '0.00') ?>" oninput="recalcNetAndSchedule()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Approved Discount</label>
                                <input type="number" step="0.01" name="discount_amount" id="discount_amount" class="form-control form-control-sm" value="<?= e($_POST['discount_amount'] ?? '0.00') ?>" oninput="recalcNetAndSchedule()">
                            </div>
                        </div>

                        <div class="mt-3 p-2 bg-white rounded border d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-muted">Net Agreed Sale Price:</span>
                            <span class="fs-5 fw-bold text-primary" id="net_price_display">Rs. 0.00</span>
                        </div>
                    </div>

                    <!-- Row 3: Payment Plan & Schedule Configuration -->
                    <div class="p-3 mb-4 rounded bg-light border">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-event me-1"></i> Installment Schedule Configuration</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Template Preset (Optional)</label>
                                <select id="plan_template_select" class="form-select form-select-sm" onchange="applyPlanTemplate(this.value)">
                                    <option value="">Custom Manual Plan</option>
                                    <?php foreach ($planTemplates as $tpl): ?>
                                        <option value="<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= e($tpl['plan_name']) ?> (<?= (int)$tpl['total_installments'] ?> <?= ucfirst(e($tpl['frequency'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Booking Date <span class="text-danger">*</span></label>
                                <input type="date" name="booking_date" id="booking_date" class="form-control form-control-sm" required value="<?= e($_POST['booking_date'] ?? date('Y-m-d')) ?>" onchange="fetchSchedulePreview()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Down Payment Amount (PKR) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="down_payment_amount" id="down_payment_amount" class="form-control form-control-sm" required value="<?= e($_POST['down_payment_amount'] ?? '0.00') ?>" oninput="fetchSchedulePreview()">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Installment Frequency</label>
                                <select name="frequency" id="frequency" class="form-select form-select-sm" onchange="fetchSchedulePreview()">
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="semi_annual">Semi-Annual</option>
                                    <option value="annual">Annual</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Number of Installments</label>
                                <input type="number" name="total_installments" id="total_installments" class="form-control form-control-sm" required value="<?= e($_POST['total_installments'] ?? '36') ?>" oninput="fetchSchedulePreview()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fixed Calendar Due Day</label>
                                <input type="number" min="1" max="31" name="due_day" id="due_day" class="form-control form-control-sm" value="<?= e($_POST['due_day'] ?? '10') ?>" oninput="fetchSchedulePreview()">
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Live Schedule Preview Table -->
                    <div class="card-hs mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table me-1"></i> Generated Installment Schedule Preview</span>
                            <span class="badge bg-light text-primary border" id="preview_summary">Awaiting calculation...</span>
                        </div>
                        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-sm table-hs mb-0" id="scheduleTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Installment Title</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Due Amount (PKR)</th>
                                    </tr>
                                </thead>
                                <tbody id="scheduleTbody">
                                    <tr><td colspan="4" class="text-center text-muted py-3">Select a plot and pricing to generate schedule.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Sales Officer</label>
                            <select name="sales_officer_id" class="form-select form-select-sm">
                                <option value="">Select Sales Officer</option>
                                <?php foreach ($officers as $off): ?>
                                    <option value="<?= (int)$off['id'] ?>"><?= e($off['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Booking Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" placeholder="e.g. Special launch discount approved" value="<?= e($_POST['remarks'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-hs-primary px-4 py-2 fw-semibold">
                            <i class="bi bi-shield-check me-1"></i> Confirm & Execute Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function handlePlotChange(plotId) {
    if (!plotId) return;
    const res = await hsmsFetch('<?= base_url("ajax/bookings/get_plot_details.php?plot_id=") ?>' + plotId);
    if (res.success && res.data) {
        const p = res.data;
        document.getElementById('base_price').value = p.base_price;
        document.getElementById('development_charges').value = p.development_charges;
        document.getElementById('extra_charges').value = p.additional_charges;
        document.getElementById('discount_amount').value = p.discount_allowed;
        recalcNetAndSchedule();
    }
}

function applyPlanTemplate(tplJson) {
    if (!tplJson) return;
    try {
        const tpl = JSON.parse(tplJson);
        document.getElementById('frequency').value = tpl.frequency;
        document.getElementById('total_installments').value = tpl.total_installments;

        const base = parseFloat(document.getElementById('base_price').value) || 0;
        const dev = parseFloat(document.getElementById('development_charges').value) || 0;
        const extra = parseFloat(document.getElementById('extra_charges').value) || 0;
        const disc = parseFloat(document.getElementById('discount_amount').value) || 0;
        const net = (base + dev + extra) - disc;

        if (tpl.down_payment_type === 'percentage') {
            const dp = (net * parseFloat(tpl.down_payment_value)) / 100.0;
            document.getElementById('down_payment_amount').value = dp.toFixed(2);
        } else {
            document.getElementById('down_payment_amount').value = parseFloat(tpl.down_payment_value).toFixed(2);
        }
        fetchSchedulePreview();
    } catch (e) {
        console.error(e);
    }
}

function recalcNetAndSchedule() {
    const base = parseFloat(document.getElementById('base_price').value) || 0;
    const dev = parseFloat(document.getElementById('development_charges').value) || 0;
    const extra = parseFloat(document.getElementById('extra_charges').value) || 0;
    const disc = parseFloat(document.getElementById('discount_amount').value) || 0;
    const net = (base + dev + extra) - disc;
    document.getElementById('net_price_display').textContent = 'Rs. ' + net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    fetchSchedulePreview();
}

async function fetchSchedulePreview() {
    const base = parseFloat(document.getElementById('base_price').value) || 0;
    const dev = parseFloat(document.getElementById('development_charges').value) || 0;
    const extra = parseFloat(document.getElementById('extra_charges').value) || 0;
    const disc = parseFloat(document.getElementById('discount_amount').value) || 0;
    const net = (base + dev + extra) - disc;

    if (net <= 0) return;

    const dp = parseFloat(document.getElementById('down_payment_amount').value) || 0;
    const totalInst = parseInt(document.getElementById('total_installments').value) || 1;
    const freq = document.getElementById('frequency').value;
    const bDate = document.getElementById('booking_date').value;
    const dueDay = document.getElementById('due_day').value;

    const formData = new FormData();
    formData.append('net_price', net);
    formData.append('down_payment_type', 'fixed');
    formData.append('down_payment_value', dp);
    formData.append('total_installments', totalInst);
    formData.append('frequency', freq);
    formData.append('start_date', bDate);
    formData.append('due_day', dueDay);

    const res = await hsmsFetch('<?= base_url("ajax/installments/generate_schedule.php") ?>', {
        method: 'POST',
        body: formData
    });

    const tbody = document.getElementById('scheduleTbody');
    if (res.success && res.data && res.data.schedule) {
        let html = '';
        res.data.schedule.forEach(row => {
            html += `<tr>
                <td>${row.installment_no}</td>
                <td><strong>${row.installment_title}</strong></td>
                <td>${row.due_date}</td>
                <td class="text-end fw-bold">Rs. ${parseFloat(row.amount_due).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
        document.getElementById('preview_summary').textContent = `Total: Rs. ${parseFloat(res.data.total_scheduled).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (${res.data.schedule.length} entries)`;
    } else {
        tbody.innerHTML = `<tr><td colspan="4" class="text-danger py-2">${res.message || 'Error generating schedule preview'}</td></tr>`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const initialPlot = document.getElementById('plot_id').value;
    if (initialPlot) {
        handlePlotChange(initialPlot);
    } else {
        recalcNetAndSchedule();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
