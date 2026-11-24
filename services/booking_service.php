<?php
/**
 * HSMS - Plot Booking & Sales Service
 * Transactional orchestrator for plot bookings, row locking, schedule generation,
 * and ledger posting.
 */

declare(strict_types=1);

require_once __DIR__ . '/installment_service.php';

class BookingService {
    /**
     * Generate next unique booking number (e.g. BK-2026-0001)
     */
    public static function generateBookingNumber(PDO $db, int $societyId): string {
        $year = date('Y');
        $prefix = "BK-{$year}-";

        $stmt = $db->prepare("
            SELECT booking_number 
            FROM `bookings` 
            WHERE `society_id` = :sid AND `booking_number` LIKE :prefix 
            ORDER BY `id` DESC 
            LIMIT 1 
            FOR UPDATE
        ");
        $stmt->execute(['sid' => $societyId, 'prefix' => "{$prefix}%"]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $num = (int)substr($last, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }

        return sprintf('%s%04d', $prefix, $num);
    }

    /**
     * Create plot booking inside an atomic database transaction
     */
    public static function createBooking(
        int $societyId,
        int $plotId,
        int $customerId,
        ?int $planTemplateId,
        string $bookingDate,
        float $basePrice,
        float $devCharges,
        float $extraCharges,
        float $discount,
        float $downPayment,
        int $installmentsCount,
        string $frequency,
        int $targetDueDay,
        ?int $salesOfficerId,
        int $createdByUserId,
        ?string $remarks = null,
        array $customMilestones = []
    ): int {
        $db = get_db_connection();

        $db->beginTransaction();
        try {
            // 1. Lock and verify plot availability
            $stmtPlot = $db->prepare("SELECT * FROM `plots` WHERE `id` = :pid AND `society_id` = :sid FOR UPDATE");
            $stmtPlot->execute(['pid' => $plotId, 'sid' => $societyId]);
            $plot = $stmtPlot->fetch();

            if (!$plot) {
                throw new RuntimeException("Selected plot does not exist in this society.");
            }
            if ($plot['status'] !== 'available') {
                throw new RuntimeException("Plot '{$plot['plot_number']}' is currently not available (Status: {$plot['status']}).");
            }

            // 2. Verify Customer is active
            $stmtCust = $db->prepare("SELECT id, full_name, status FROM `customers` WHERE `id` = :cid");
            $stmtCust->execute(['cid' => $customerId]);
            $customer = $stmtCust->fetch();

            if (!$customer || $customer['status'] !== 'active') {
                throw new RuntimeException("Selected customer is not active or blacklisted.");
            }

            // 3. Reconcile calculations
            $netSalePrice = round(($basePrice + $devCharges + $extraCharges) - $discount, 2);
            if ($netSalePrice <= 0) {
                throw new RuntimeException("Net sale price must be greater than zero.");
            }
            if ($downPayment < 0 || $downPayment > $netSalePrice) {
                throw new RuntimeException("Down payment cannot be less than 0 or greater than Net Sale Price.");
            }

            // 4. Generate unique booking number
            $bookingNumber = self::generateBookingNumber($db, $societyId);

            // 5. Insert Booking record
            $stmtBk = $db->prepare("
                INSERT INTO `bookings` (
                    `booking_number`, `society_id`, `plot_id`, `customer_id`, `plan_template_id`,
                    `booking_date`, `base_price`, `development_charges`, `extra_charges`,
                    `discount_amount`, `net_sale_price`, `down_payment_amount`, `down_payment_status`,
                    `total_paid`, `remaining_balance`, `advance_balance`, `sales_officer_id`,
                    `status`, `remarks`, `created_by`
                ) VALUES (
                    :bk_num, :sid, :pid, :cid, :ptid,
                    :bdate, :base, :dev, :extra,
                    :disc, :net_sale, :dp, 'pending',
                    0.00, :rem_bal, 0.00, :officer,
                    'active', :remarks, :uid
                )
            ");
            $stmtBk->execute([
                'bk_num'   => $bookingNumber,
                'sid'      => $societyId,
                'pid'      => $plotId,
                'cid'      => $customerId,
                'ptid'     => $planTemplateId ?: null,
                'bdate'    => $bookingDate,
                'base'     => $basePrice,
                'dev'      => $devCharges,
                'extra'    => $extraCharges,
                'disc'     => $discount,
                'net_sale' => $netSalePrice,
                'dp'       => $downPayment,
                'rem_bal'  => $netSalePrice,
                'officer'  => $salesOfficerId ?: null,
                'remarks'  => $remarks ?: null,
                'uid'      => $createdByUserId
            ]);
            $bookingId = (int)$db->lastInsertId();

            // 6. Generate and save Installment Schedule
            $schedule = InstallmentService::generateSchedule(
                $netSalePrice,
                $downPayment,
                $installmentsCount,
                $frequency,
                $bookingDate,
                $targetDueDay,
                $customMilestones
            );
            InstallmentService::saveScheduleForBooking($db, $bookingId, $schedule);

            // 7. Post initial Debit on Customer Ledger
            $stmtLedger = $db->prepare("
                INSERT INTO `customer_ledger` (
                    `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                    `transaction_type`, `reference_id`, `voucher_no`, `description`,
                    `debit`, `credit`, `running_balance`
                ) VALUES (
                    :sid, :cid, :bid, :pid, :tdate,
                    'booking_debit', :ref_id, :vnum, :desc,
                    :debit, 0.00, :balance
                )
            ");
            $stmtLedger->execute([
                'sid'     => $societyId,
                'cid'     => $customerId,
                'bid'     => $bookingId,
                'pid'     => $plotId,
                'tdate'   => $bookingDate,
                'ref_id'  => $bookingId,
                'vnum'    => $bookingNumber,
                'desc'    => "Plot Sale Booking {$bookingNumber} (Plot #{$plot['plot_number']})",
                'debit'   => $netSalePrice,
                'balance' => $netSalePrice
            ]);

            // 8. Update Plot status to 'booked'
            $stmtUpdPlot = $db->prepare("UPDATE `plots` SET `status` = 'booked' WHERE `id` = :pid");
            $stmtUpdPlot->execute(['pid' => $plotId]);

            // 9. Commit Transaction
            $db->commit();

            log_audit('BOOKING_CREATED', 'bookings', $bookingId, null, [
                'booking_number' => $bookingNumber,
                'plot_number'    => $plot['plot_number'],
                'customer_id'    => $customerId,
                'net_sale_price' => $netSalePrice
            ], $societyId);

            return $bookingId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
