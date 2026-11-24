<?php
/**
 * HSMS - Automated Test Suite: Phase 11 (Notification Engine & Settings)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/notification_service.php';

$cookieFile = sys_get_temp_dir() . '/hsms_test_cookie.txt';
$baseUrl = 'http://localhost/HSMS';

function test_assert(bool $condition, string $description): void {
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        exit(1);
    }
}

echo "=== HSMS Test Suite: Phase 11 (Notification Engine & Settings) ===\n\n";

$db = get_db_connection();

// 1. Template validation
echo "--- Step 1: Default Template Verification ---\n";
test_assert(isset(NotificationService::DEFAULT_TEMPLATES['BOOKING_CONFIRMATION']), "Booking confirmation template exists");
test_assert(isset(NotificationService::DEFAULT_TEMPLATES['PAYMENT_RECEIPT']), "Payment receipt template exists");
test_assert(isset(NotificationService::DEFAULT_TEMPLATES['INSTALLMENT_DUE']), "Installment due reminder template exists");
test_assert(isset(NotificationService::DEFAULT_TEMPLATES['OVERDUE_DEMAND']), "Overdue demand notice template exists");
test_assert(isset(NotificationService::DEFAULT_TEMPLATES['TRANSFER_COMPLETED']), "Transfer notice template exists");

// 2. Template variable interpolation
echo "\n--- Step 2: Variable Interpolation ---\n";
$tpl = "Hello {customer_name}, your balance for Plot #{plot_number} in {society_name} is Rs. {amount}.";
$vars = [
    'customer_name' => 'Tariq Mehmood',
    'plot_number'   => 'A-101',
    'society_name'  => 'Green Valley Residency',
    'amount'        => '50,000.00'
];
$rendered = NotificationService::renderTemplate($tpl, $vars);
test_assert(
    $rendered === "Hello Tariq Mehmood, your balance for Plot #A-101 in Green Valley Residency is Rs. 50,000.00.",
    "Template interpolation correctly replaced all tags"
);

// 3. WhatsApp phone number standardization
echo "\n--- Step 3: Phone Number Formatting (E.164 WhatsApp) ---\n";
$phone1 = "03001234567";
$phone2 = "+92 321-7654321";
$phone3 = "3339876543";
$phone4 = "923451122334";

test_assert(NotificationService::formatPhoneForWhatsApp($phone1) === '923001234567', "0300... converts to 92300...");
test_assert(NotificationService::formatPhoneForWhatsApp($phone2) === '923217654321', "+92 321-... cleans punctuation to 92321...");
test_assert(NotificationService::formatPhoneForWhatsApp($phone3) === '923339876543', "10-digit 333... prepends 92");
test_assert(NotificationService::formatPhoneForWhatsApp($phone4) === '923451122334', "Already 92... is preserved");

// 4. Click-to-Chat WhatsApp deep link generation
echo "\n--- Step 4: WhatsApp Click-to-Chat Link Generation ---\n";
$waLink = NotificationService::generateWhatsAppLink("03001234567", "Dear Customer, Plot #101 payment is due.");
test_assert(
    str_starts_with($waLink, 'https://wa.me/923001234567?text='),
    "Generated URL points to https://wa.me/923001234567"
);
test_assert(
    str_contains($waLink, rawurlencode("Dear Customer, Plot #101 payment is due.")),
    "Message text is properly URL-encoded in wa.me link"
);

// 5. Audit Logging to DB
echo "\n--- Step 5: Notification Logging into Database ---\n";
$societyId = 1;
$logId = NotificationService::logNotification(
    $societyId,
    null,
    '03001234567',
    'whatsapp',
    'INSTALLMENT_DUE',
    'Dear Customer, test message',
    'manual_link_generated',
    'REF-TEST-001'
);
test_assert($logId > 0, "logNotification returned positive log ID ({$logId})");

$stmt = $db->prepare("SELECT * FROM `notification_logs` WHERE `id` = :id");
$stmt->execute(['id' => $logId]);
$logRow = $stmt->fetch();
test_assert(!empty($logRow), "Log record exists in `notification_logs` table");
test_assert($logRow['channel'] === 'whatsapp', "Channel matches 'whatsapp'");
test_assert($logRow['status'] === 'manual_link_generated', "Status matches 'manual_link_generated'");
test_assert($logRow['external_ref'] === 'REF-TEST-001', "External ref matches 'REF-TEST-001'");

// 6. Simulated SMS Dispatch Logging
$smsLogId = NotificationService::logNotification(
    $societyId,
    null,
    '+923219988776',
    'sms',
    'SMS_BROADCAST',
    'Broadcast notification to all members.',
    'sent',
    'SMS-GATEWAY-12345'
);
test_assert($smsLogId > 0, "Simulated SMS returned positive log ID ({$smsLogId})");

// 7. HTTP UI endpoint verification
echo "\n--- Step 7: HTTP UI Endpoint Verification ---\n";
$ch = curl_init($baseUrl . '/modules/settings/notifications.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

test_assert($httpCode === 200, "GET /modules/settings/notifications.php returned HTTP 200");
test_assert(str_contains($response, 'Notification Engine & Customer Messaging'), "Page contains header title");
test_assert(str_contains($response, 'WhatsApp Direct Message & Link Generator'), "Page contains WhatsApp generator card");
test_assert(str_contains($response, 'SMS Gateway Simulator'), "Page contains SMS simulator card");
test_assert(str_contains($response, 'Outbound Notification Delivery Log'), "Page contains logs table");

echo "\n>>> PHASE 11 ALL TESTS PASSED SUCCESSFULLY! <<<\n";
