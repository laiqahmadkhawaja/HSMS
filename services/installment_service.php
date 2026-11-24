<?php
/**
 * HSMS - Installment Engine Service
 * Centralized calculation engine for payment schedules, rounding adjustments,
 * custom balloon milestones, and revision snapshots.
 */

declare(strict_types=1);

class InstallmentService {
    /**
     * Compute Down Payment amount based on type (percentage vs fixed)
     */
    public static function calculateDownPayment(float $netPrice, string $dpType, float $dpValue): float {
        if ($dpType === 'percentage') {
            $amount = ($netPrice * $dpValue) / 100.0;
        } else {
            $amount = $dpValue;
        }
        return round($amount, 2);
    }

    /**
     * Generate complete installment schedule
     * Guarantees Down Payment + SUM(Installments) === Net Sale Price with penny precision
     */
    public static function generateSchedule(
        float $netPrice,
        float $downPayment,
        int $totalInstallments,
        string $frequency = 'monthly',
        ?string $startDate = null,
        int $targetDueDay = 0,
        array $milestonePayments = []
    ): array {
        $netPrice = round($netPrice, 2);
        $downPayment = round($downPayment, 2);

        if ($netPrice <= 0) {
            throw new InvalidArgumentException("Net plot price must be greater than zero.");
        }
        if ($downPayment < 0 || $downPayment > $netPrice) {
            throw new InvalidArgumentException("Down payment must be between 0 and Net Plot Price.");
        }
        if ($totalInstallments < 1) {
            throw new InvalidArgumentException("Total installments must be at least 1.");
        }

        $startDateStr = $startDate ?: date('Y-m-d');
        $baseDate = new DateTime($startDateStr);

        $schedule = [];

        // 1. Installment #0: Down Payment
        $schedule[] = [
            'installment_no'    => 0,
            'installment_title' => 'Down Payment / Booking Advance',
            'due_date'          => $baseDate->format('Y-m-d'),
            'amount_due'        => $downPayment,
            'principal_amount'  => $downPayment,
            'charges_amount'    => 0.00,
            'amount_paid'       => 0.00,
            'balance_due'       => $downPayment,
            'status'            => ($downPayment > 0) ? 'pending' : 'paid',
            'is_custom'         => 0
        ];

        $remainingToSchedule = round($netPrice - $downPayment, 2);

        // Deduct any pre-specified milestones (e.g. Balloon payment at month 12, possession charge)
        $milestoneSum = 0.0;
        foreach ($milestonePayments as $ms) {
            $msAmount = round((float)($ms['amount'] ?? 0), 2);
            $milestoneSum += $msAmount;
        }

        if ($milestoneSum > $remainingToSchedule) {
            throw new InvalidArgumentException("Sum of custom milestones (" . number_format($milestoneSum, 2) . ") exceeds remaining balance (" . number_format($remainingToSchedule, 2) . ").");
        }

        $amountForRegular = round($remainingToSchedule - $milestoneSum, 2);
        $rawPerInstallment = round($amountForRegular / $totalInstallments, 2);

        $runningRegularSum = 0.0;
        $currentDate = clone $baseDate;

        for ($i = 1; $i <= $totalInstallments; $i++) {
            // Compute next due date taking into account short months and leap years
            $currentDate = self::calculateNextDueDate($currentDate, $frequency, $targetDueDay);

            $dueAmount = $rawPerInstallment;
            $runningRegularSum += $dueAmount;

            // Rounding adjustment on final installment
            if ($i === $totalInstallments) {
                $roundingDifference = round($amountForRegular - $runningRegularSum, 2);
                $dueAmount = round($dueAmount + $roundingDifference, 2);
            }

            $schedule[] = [
                'installment_no'    => $i,
                'installment_title' => "Installment #{$i} (" . ucfirst($frequency) . ")",
                'due_date'          => $currentDate->format('Y-m-d'),
                'amount_due'        => $dueAmount,
                'principal_amount'  => $dueAmount,
                'charges_amount'    => 0.00,
                'amount_paid'       => 0.00,
                'balance_due'       => $dueAmount,
                'status'            => 'pending',
                'is_custom'         => 0
            ];
        }

        // Add custom milestone installments if provided
        $msIndex = $totalInstallments + 1;
        foreach ($milestonePayments as $ms) {
            $msAmount = round((float)$ms['amount'], 2);
            $schedule[] = [
                'installment_no'    => $msIndex++,
                'installment_title' => $ms['title'] ?? 'Special Milestone Payment',
                'due_date'          => $ms['due_date'],
                'amount_due'        => $msAmount,
                'principal_amount'  => $msAmount,
                'charges_amount'    => 0.00,
                'amount_paid'       => 0.00,
                'balance_due'       => $msAmount,
                'status'            => 'pending',
                'is_custom'         => 1
            ];
        }

        // Final verification check
        if (!self::validateScheduleTotal($netPrice, $schedule)) {
            $actualSum = self::getScheduleTotalSum($schedule);
            throw new RuntimeException("Schedule mathematical mismatch! Net Price: {$netPrice}, Schedule Sum: {$actualSum}");
        }

        return $schedule;
    }

    /**
     * Compute next calendar due date safely handling leap years and month-ends
     */
    public static function calculateNextDueDate(DateTime $current, string $frequency, int $targetDay = 0): DateTime {
        $next = clone $current;

        $monthsToAdd = match($frequency) {
            'quarterly'   => 3,
            'semi_annual' => 6,
            'annual'      => 12,
            default       => 1
        };

        $currentYear = (int)$next->format('Y');
        $currentMonth = (int)$next->format('m');
        $currentDay = $targetDay > 0 ? $targetDay : (int)$next->format('d');

        $targetMonth = $currentMonth + $monthsToAdd;
        $targetYear = $currentYear + (int)floor(($targetMonth - 1) / 12);
        $targetMonth = (($targetMonth - 1) % 12) + 1;

        // Check max days in target month (e.g. Feb has 28 or 29, April has 30)
        $maxDays = (int)cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
        $dayToUse = min($currentDay, $maxDays);

        $next->setDate($targetYear, $targetMonth, $dayToUse);
        return $next;
    }

    /**
     * Validate that SUM(all scheduled amounts) exactly equals Net Price
     */
    public static function validateScheduleTotal(float $netPrice, array $schedule): bool {
        $sum = self::getScheduleTotalSum($schedule);
        return (abs($sum - round($netPrice, 2)) < 0.01);
    }

    /**
     * Calculate sum of all schedule items
     */
    public static function getScheduleTotalSum(array $schedule): float {
        $sum = 0.0;
        foreach ($schedule as $item) {
            $sum += (float)($item['amount_due'] ?? 0);
        }
        return round($sum, 2);
    }

    /**
     * Save generated schedule into `installment_schedules` table inside an active transaction
     */
    public static function saveScheduleForBooking(PDO $db, int $bookingId, array $schedule): void {
        $stmt = $db->prepare("
            INSERT INTO `installment_schedules` (
                `booking_id`, `installment_no`, `installment_title`, `due_date`,
                `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`,
                `balance_due`, `status`, `is_custom`
            ) VALUES (
                :bid, :ino, :title, :due_date,
                :due, :principal, :charges, :paid,
                :balance, :status, :is_custom
            )
        ");

        foreach ($schedule as $row) {
            $stmt->execute([
                'bid'       => $bookingId,
                'ino'       => $row['installment_no'],
                'title'     => $row['installment_title'],
                'due_date'  => $row['due_date'],
                'due'       => $row['amount_due'],
                'principal' => $row['principal_amount'] ?? $row['amount_due'],
                'charges'   => $row['charges_amount'] ?? 0.00,
                'paid'      => $row['amount_paid'] ?? 0.00,
                'balance'   => $row['balance_due'] ?? $row['amount_due'],
                'status'    => $row['status'] ?? 'pending',
                'is_custom' => $row['is_custom'] ?? 0
            ]);
        }
    }

    /**
     * Revise an existing schedule safely preserving paid records and taking a snapshot
     */
    public static function reviseSchedule(
        PDO $db,
        int $bookingId,
        array $newFutureInstallments,
        string $reason,
        int $approvedByUserId
    ): void {
        // 1. Fetch current schedule to create audit snapshot
        $stmtCurr = $db->prepare("SELECT * FROM `installment_schedules` WHERE `booking_id` = :bid ORDER BY `installment_no` ASC");
        $stmtCurr->execute(['bid' => $bookingId]);
        $oldSchedule = $stmtCurr->fetchAll();

        if (empty($oldSchedule)) {
            throw new RuntimeException("No existing schedule found for booking ID {$bookingId}.");
        }

        // Determine next revision number
        $stmtRev = $db->prepare("SELECT COALESCE(MAX(revision_number), 0) + 1 FROM `installment_revisions` WHERE `booking_id` = :bid");
        $stmtRev->execute(['bid' => $bookingId]);
        $nextRevNo = (int)$stmtRev->fetchColumn();

        // 2. Insert audit revision snapshot
        $stmtInsRev = $db->prepare("
            INSERT INTO `installment_revisions` (`booking_id`, `revision_number`, `reason`, `snapshot_json`, `approved_by`)
            VALUES (:bid, :rno, :reason, :snapshot, :uid)
        ");
        $stmtInsRev->execute([
            'bid'      => $bookingId,
            'rno'      => $nextRevNo,
            'reason'   => $reason,
            'snapshot' => json_encode($oldSchedule, JSON_PRETTY_PRINT),
            'uid'      => $approvedByUserId
        ]);

        // 3. Delete only pending/unpaid installments (Paid installments cannot be altered!)
        $delStmt = $db->prepare("DELETE FROM `installment_schedules` WHERE `booking_id` = :bid AND `amount_paid` = 0 AND `status` != 'paid'");
        $delStmt->execute(['bid' => $bookingId]);

        // 4. Insert new future installments
        $stmtIns = $db->prepare("
            INSERT INTO `installment_schedules` (
                `booking_id`, `installment_no`, `installment_title`, `due_date`,
                `amount_due`, `principal_amount`, `charges_amount`, `amount_paid`,
                `balance_due`, `status`, `is_custom`
            ) VALUES (
                :bid, :ino, :title, :due_date,
                :due, :principal, :charges, 0.00,
                :bal_due, 'pending', :is_custom
            )
        ");

        foreach ($newFutureInstallments as $row) {
            $stmtIns->execute([
                'bid'       => $bookingId,
                'ino'       => $row['installment_no'],
                'title'     => $row['installment_title'],
                'due_date'  => $row['due_date'],
                'due'       => $row['amount_due'],
                'principal' => $row['principal_amount'] ?? $row['amount_due'],
                'charges'   => $row['charges_amount'] ?? 0.00,
                'bal_due'   => $row['amount_due'],
                'is_custom' => $row['is_custom'] ?? 0
            ]);
        }
    }
}
