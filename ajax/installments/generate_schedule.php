<?php
/**
 * HSMS - AJAX Endpoint for Schedule Generation & Preview
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../services/installment_service.php';
require_login();

$netPrice = (float)($_POST['net_price'] ?? 0);
$dpType = (string)($_POST['down_payment_type'] ?? 'percentage');
$dpValue = (float)($_POST['down_payment_value'] ?? 0);
$installmentsCount = (int)($_POST['total_installments'] ?? 1);
$frequency = (string)($_POST['frequency'] ?? 'monthly');
$startDate = !empty($_POST['start_date']) ? (string)$_POST['start_date'] : date('Y-m-d');
$dueDay = (int)($_POST['due_day'] ?? 0);

$milestonesRaw = $_POST['milestones'] ?? '[]';
$milestones = is_string($milestonesRaw) ? json_decode($milestonesRaw, true) : (array)$milestonesRaw;
if (!is_array($milestones)) {
    $milestones = [];
}

if ($netPrice <= 0) {
    json_response(false, 'Please provide a valid net plot price.', [], ['net_price' => 'Must be greater than 0']);
}

try {
    $dpAmount = InstallmentService::calculateDownPayment($netPrice, $dpType, $dpValue);
    $schedule = InstallmentService::generateSchedule(
        $netPrice,
        $dpAmount,
        $installmentsCount,
        $frequency,
        $startDate,
        $dueDay,
        $milestones
    );

    $totalScheduled = InstallmentService::getScheduleTotalSum($schedule);

    json_response(true, 'Schedule generated successfully', [
        'net_price'          => $netPrice,
        'down_payment'       => $dpAmount,
        'installments_count' => $installmentsCount,
        'frequency'          => $frequency,
        'total_scheduled'    => $totalScheduled,
        'schedule'           => $schedule
    ]);
} catch (Throwable $e) {
    json_response(false, $e->getMessage(), [], ['error' => $e->getMessage()], 400);
}
