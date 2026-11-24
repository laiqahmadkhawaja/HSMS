<?php
/**
 * HSMS - Notification Engine, Templates & Messaging Logs
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/notification_service.php';
require_permission('settings_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$society = get_active_society();

$errors = [];
$generatedLink = '';
$generatedMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'compose_whatsapp') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $templateKey = trim($_POST['template_key'] ?? 'INSTALLMENT_DUE');
        $customText = trim($_POST['custom_message'] ?? '');

        if (empty($phone)) {
            $errors['phone'] = 'Recipient phone number is required.';
        }

        if (empty($errors)) {
            $msg = !empty($customText) ? $customText : (NotificationService::DEFAULT_TEMPLATES[$templateKey] ?? 'Greetings from Society Management.');

            // Fetch customer data if selected for auto replacement
            if ($customerId > 0) {
                $stmtC = $db->prepare("SELECT full_name, mobile, whatsapp FROM `customers` WHERE `id` = :cid");
                $stmtC->execute(['cid' => $customerId]);
                $cust = $stmtC->fetch();
                if ($cust) {
                    $msg = NotificationService::renderTemplate($msg, [
                        'customer_name' => $cust['full_name'],
                        'society_name'  => $society['name'] ?? 'Society',
                        'plot_number'   => '101',
                        'block_name'    => 'Sector A',
                        'booking_number'=> 'BK-2026-0001',
                        'amount_due'    => '45,000.00',
                        'due_date'      => date('d M Y', strtotime('+7 days'))
                    ]);
                }
            } else {
                $msg = NotificationService::renderTemplate($msg, [
                    'customer_name' => 'Valued Member',
                    'society_name'  => $society['name'] ?? 'Society',
                    'plot_number'   => 'Plot',
                    'block_name'    => 'Sector',
                    'booking_number'=> 'BK',
                    'amount_due'    => '0.00',
                    'due_date'      => date('d M Y')
                ]);
            }

            $generatedMessage = $msg;
            $generatedLink = NotificationService::generateWhatsAppLink($phone, $msg);

            // Log event
            NotificationService::logNotification(
                $activeSocId,
                $customerId > 0 ? $customerId : null,
                $phone,
                'whatsapp',
                $templateKey,
                $msg,
                'manual_link_generated'
            );
        }
    } elseif ($action === 'simulate_sms') {
        $phone = trim($_POST['sms_phone'] ?? '');
        $smsBody = trim($_POST['sms_body'] ?? '');

        if (empty($phone) || empty($smsBody)) {
            $errors['sms'] = 'Phone and SMS body are required.';
        } else {
            NotificationService::logNotification(
                $activeSocId,
                null,
                $phone,
                'sms',
                'SMS_BROADCAST',
                $smsBody,
                'sent',
                'SIM-GATEWAY-' . time()
            );
            redirect_with_message(base_url('modules/settings/notifications.php'), 'Test SMS simulated and logged successfully!', 'success');
        }
    }
}

// Fetch Customers for selector
$stmtCust = $db->query("SELECT id, full_name, mobile, whatsapp FROM `customers` WHERE `status` = 'active' ORDER BY `full_name` ASC LIMIT 50");
$customers = $stmtCust->fetchAll();

// Fetch Recent Notification Logs
$stmtLogs = $db->prepare("
    SELECT nl.*, c.full_name as customer_name
    FROM `notification_logs` nl
    LEFT JOIN `customers` c ON nl.customer_id = c.id
    WHERE nl.society_id = :sid
    ORDER BY nl.id DESC
    LIMIT 20
");
$stmtLogs->execute(['sid' => $activeSocId]);
$logs = $stmtLogs->fetchAll();

$pageTitle = 'Notification Engine & WhatsApp';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Notification Engine & Customer Messaging</h4>
        <p class="text-muted small mb-0">SMS gateway stubs, WhatsApp direct linker, and delivery history for <strong><?= e($society['name'] ?? 'Society') ?></strong></p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- WhatsApp Direct Generator -->
    <div class="col-lg-7">
        <div class="card-hs h-100">
            <div class="card-header bg-success text-white d-flex align-items-center">
                <i class="bi bi-whatsapp fs-5 me-2"></i>
                <span class="fw-bold">WhatsApp Direct Message & Link Generator</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="compose_whatsapp">

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Select Registered Customer</label>
                            <select name="customer_id" class="form-select form-select-sm" onchange="autoFillPhone(this)">
                                <option value="">Custom Recipient</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" data-phone="<?= e($c['whatsapp'] ?: $c['mobile']) ?>">
                                        <?= e($c['full_name']) ?> (<?= e($c['mobile']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">WhatsApp Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="recipientPhone" class="form-control form-control-sm" placeholder="e.g. 03001234567 or +923001234567" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Choose Message Template</label>
                        <select name="template_key" id="templateSelector" class="form-select form-select-sm" onchange="loadTemplateText(this.value)">
                            <option value="INSTALLMENT_DUE">Installment Due Reminder</option>
                            <option value="OVERDUE_DEMAND">Overdue Final Demand Notice</option>
                            <option value="PAYMENT_RECEIPT">Payment Receipt Confirmation</option>
                            <option value="BOOKING_CONFIRMATION">Booking Confirmation Notice</option>
                            <option value="TRANSFER_COMPLETED">Plot Transfer Deed Notice</option>
                            <option value="CUSTOM">Custom Message</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Message Content</label>
                        <textarea name="custom_message" id="messageBox" class="form-control form-control-sm" rows="3"><?= e(NotificationService::DEFAULT_TEMPLATES['INSTALLMENT_DUE']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Standard variables: {customer_name}, {plot_number}, {society_name}</span>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-semibold">
                            <i class="bi bi-link-45deg me-1"></i> Generate WhatsApp Link
                        </button>
                    </div>
                </form>

                <?php if (!empty($generatedLink)): ?>
                    <div class="alert alert-success mt-3 p-3">
                        <div class="fw-bold mb-1"><i class="bi bi-check2-circle me-1"></i> WhatsApp Link Generated:</div>
                        <div class="bg-white p-2 rounded border small text-break font-monospace mb-2">
                            <?= e($generatedLink) ?>
                        </div>
                        <div class="text-end">
                            <a href="<?= e($generatedLink) ?>" target="_blank" class="btn btn-success btn-sm px-3 fw-bold">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Open Chat on WhatsApp
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SMS Gateway Stub -->
    <div class="col-lg-5">
        <div class="card-hs h-100">
            <div class="card-header bg-dark text-white d-flex align-items-center">
                <i class="bi bi-chat-dots-fill fs-5 me-2"></i>
                <span class="fw-bold">SMS Gateway Simulator (Local / Live)</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="simulate_sms">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">SMS Masking / Sender ID</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= e($society['society_code'] ?? 'HSMS') ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Recipient Mobile Number</label>
                        <input type="text" name="sms_phone" class="form-control form-control-sm" placeholder="e.g. +92 300 1234567" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">SMS Text Body (Max 160 Chars)</label>
                        <textarea name="sms_body" class="form-control form-control-sm" rows="3" maxlength="160" placeholder="Type SMS content..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-outline-dark btn-sm w-100 fw-semibold">
                        <i class="bi bi-send me-1"></i> Simulate Outbound SMS Dispatch
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Notification Audit Logs -->
<div class="card-hs">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-clock-history me-1 text-primary"></i> Outbound Notification Delivery Log</span>
        <span class="badge bg-light text-muted border">Recent 20 Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hs align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 150px;">Timestamp</th>
                        <th>Recipient / Customer</th>
                        <th>Channel</th>
                        <th>Notice Type</th>
                        <th>Message Preview</th>
                        <th>Status</th>
                        <th>External Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No notification history recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="small text-muted"><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($l['customer_name'] ?: 'Direct Phone') ?></div>
                                    <div class="text-muted small"><?= e($l['recipient']) ?></div>
                                </td>
                                <td>
                                    <?php if ($l['channel'] === 'whatsapp'): ?>
                                        <span class="badge bg-success"><i class="bi bi-whatsapp me-1"></i> WhatsApp</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark"><i class="bi bi-chat-text me-1"></i> SMS</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($l['message_type']) ?></span></td>
                                <td class="small text-muted" style="max-width: 250px;">
                                    <?= e($l['message_body']) ?>
                                </td>
                                <td>
                                    <?php if ($l['status'] === 'sent' || $l['status'] === 'manual_link_generated'): ?>
                                        <span class="badge bg-success">Delivered / Ready</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= ucfirst($l['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace small text-muted"><?= e($l['external_ref'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const templates = <?= json_encode(NotificationService::DEFAULT_TEMPLATES) ?>;

function autoFillPhone(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.phone) {
        document.getElementById('recipientPhone').value = opt.dataset.phone;
    }
}

function loadTemplateText(key) {
    const box = document.getElementById('messageBox');
    if (templates[key]) {
        box.value = templates[key];
    } else {
        box.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
