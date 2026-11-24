<?php
/**
 * HSMS - Plot Cancellation & Refund Service
 * Manages plot cancellations, inventory release back to 'available',
 * policy deductions, refund payable calculations, and double-entry accounting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class CancellationService {
    /**
     * Generate sequential unique cancellation number
     */
    public static function generateCancellationNumber(PDO $db, int $societyId): string {
        $year = date('Y');

        $stmtSoc = $db->prepare("SELECT society_code FROM `societies` WHERE `id` = :sid LIMIT 1");
        $stmtSoc->execute(['sid' => $societyId]);
        $socCode = $stmtSoc->fetchColumn() ?: 'SOC';

        $stmtCnt = $db->prepare("
            SELECT COUNT(*) 
            FROM `cancellations` c
            JOIN `bookings` b ON c.booking_id = b.id
            WHERE b.society_id = :sid AND YEAR(c.`created_at`) = :yr
        ");
        $stmtCnt->execute(['sid' => $societyId, 'yr' => $year]);
        $nextNum = ((int)$stmtCnt->fetchColumn()) + 1;

        return sprintf('CNL-%s-%s-%04d', $socCode, $year, $nextNum);
    }

    /**
     * Process plot cancellation and release inventory back to available
     */
    public static function processCancellation(
        int $societyId,
        int $bookingId,
        float $deductionAmount,
        ?string $cancellationDate = null,
        string $reason = '',
        int $approvedByUserId = 1
    ): int {
        $db = get_db_connection();
        $cancellationDate = $cancellationDate ?: date('Y-m-d');
        $deductionAmount = round($deductionAmount, 2);
        $reason = trim($reason);

        if ($deductionAmount < 0) {
            throw new InvalidArgumentException("Deduction amount cannot be negative.");
        }
        if (empty($reason)) {
            throw new InvalidArgumentException("A valid reason for cancellation is required.");
        }

        $db->beginTransaction();
        try {
            // 1. Lock Booking
            $stmtBk = $db->prepare("
                SELECT bk.*, p.plot_number, p.id as p_id, b.block_name 
                FROM `bookings` bk
                JOIN `plots` p ON bk.plot_id = p.id
                JOIN `blocks` b ON p.block_id = b.id
                WHERE bk.id = :bid AND bk.society_id = :sid 
                FOR UPDATE
            ");
            $stmtBk->execute(['bid' => $bookingId, 'sid' => $societyId]);
            $booking = $stmtBk->fetch();

            if (!$booking) {
                throw new RuntimeException("Booking contract #{$bookingId} not found in active society.");
            }
            if ($booking['status'] !== 'active') {
                throw new RuntimeException("Only active booking contracts can be cancelled (Current status: {$booking['status']}).");
            }

            $totalPaid = round((float)$booking['total_paid'], 2);
            if ($deductionAmount > $totalPaid) {
                throw new InvalidArgumentException("Deduction amount (" . number_format($deductionAmount, 2) . ") cannot exceed total paid (" . number_format($totalPaid, 2) . ").");
            }

            $refundPayable = round($totalPaid - $deductionAmount, 2);

            // 2. Lock Plot
            $plotId = (int)$booking['plot_id'];
            $stmtPlot = $db->prepare("SELECT * FROM `plots` WHERE `id` = :pid FOR UPDATE");
            $stmtPlot->execute(['pid' => $plotId]);
            $plot = $stmtPlot->fetch();

            if (!$plot) {
                throw new RuntimeException("Associated plot record missing.");
            }

            // 3. Generate Cancellation Number
            $cancellationNumber = self::generateCancellationNumber($db, $societyId);

            // 4. Insert into cancellations
            $stmtInsCnl = $db->prepare("
                INSERT INTO `cancellations` (
                    `cancellation_number`, `booking_id`, `plot_id`, `customer_id`,
                    `cancellation_date`, `total_paid_at_cancel`, `deduction_amount`,
                    `refund_amount_payable`, `refund_paid_amount`, `refund_status`,
                    `approval_status`, `approved_by`, `reason`
                ) VALUES (
                    :cnum, :bid, :pid, :cid,
                    :cdate, :tpaid, :deduct,
                    :refund, 0.00, 'pending',
                    'approved', :uid, :reason
                )
            ");
            $stmtInsCnl->execute([
                'cnum'   => $cancellationNumber,
                'bid'    => $bookingId,
                'pid'    => $plotId,
                'cid'    => $booking['customer_id'],
                'cdate'  => $cancellationDate,
                'tpaid'  => $totalPaid,
                'deduct' => $deductionAmount,
                'refund' => $refundPayable,
                'uid'    => $approvedByUserId,
                'reason' => $reason
            ]);
            $cancellationId = (int)$db->lastInsertId();

            // 5. Update Booking status to 'cancelled'
            $stmtUpdBk = $db->prepare("
                UPDATE `bookings`
                SET `status` = 'cancelled',
                    `remarks` = CONCAT(COALESCE(`remarks`, ''), ' | Cancelled on ', :cdate, ' (Ref: ', :cnum, ')')
                WHERE `id` = :bid
            ");
            $stmtUpdBk->execute([
                'cdate' => $cancellationDate,
                'cnum'  => $cancellationNumber,
                'bid'   => $bookingId
            ]);

            // 6. Release Plot back to 'available' inventory
            $stmtUpdPlot = $db->prepare("
                UPDATE `plots`
                SET `status` = 'available',
                    `remarks` = CONCAT(COALESCE(`remarks`, ''), ' | Relisted after cancellation of ', :bk_num)
                WHERE `id` = :pid
            ");
            $stmtUpdPlot->execute([
                'bk_num' => $booking['booking_number'],
                'pid'    => $plotId
            ]);

            // 7. Customer Ledger Entry: Close out contract running balance
            $stmtPrevLedger = $db->prepare("
                SELECT running_balance 
                FROM `customer_ledger` 
                WHERE `customer_id` = :cid AND `booking_id` = :bid 
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmtPrevLedger->execute(['cid' => $booking['customer_id'], 'bid' => $bookingId]);
            $prevBal = (float)($stmtPrevLedger->fetchColumn() ?: $booking['remaining_balance']);

            $stmtLedger = $db->prepare("
                INSERT INTO `customer_ledger` (
                    `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                    `transaction_type`, `reference_id`, `voucher_no`, `description`,
                    `debit`, `credit`, `running_balance`
                ) VALUES (
                    :sid, :cid, :bid, :pid, :tdate,
                    'cancellation_credit', :ref_id, :vnum, :desc,
                    0.00, :credit, 0.00
                )
            ");
            $stmtLedger->execute([
                'sid'    => $societyId,
                'cid'    => $booking['customer_id'],
                'bid'    => $bookingId,
                'pid'    => $plotId,
                'tdate'  => $cancellationDate,
                'ref_id' => $cancellationId,
                'vnum'   => $cancellationNumber,
                'desc'   => "Cancellation of Plot #{$booking['plot_number']} ({$cancellationNumber})",
                'credit' => $prevBal
            ]);

            // 8. Accounting Journal Entry
            // Rule:
            // Debit Customer Plot Receivables (remaining balance reversal)
            // Credit Customer Refund Payables (refund payable amount)
            // Credit Cancellation Charges Income (deduction amount)
            if ($totalPaid > 0) {
                $stmtJVNum = $db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM `journal_entries`");
                $stmtJVNum->execute();
                $jvNum = sprintf('JV-%s-%04d', date('Y'), (int)$stmtJVNum->fetchColumn());

                // Find Cancellation Charges Income account (code '4004', ID 13)
                $stmtIncomeAcc = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '4004' LIMIT 1");
                $incomeAccountId = (int)($stmtIncomeAcc->fetchColumn() ?: 13);

                // Find Customer Refund Payables account (code '2010', ID 7)
                $stmtRefundAcc = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '2010' LIMIT 1");
                $refundAccountId = (int)($stmtRefundAcc->fetchColumn() ?: 7);

                // Find Customer Receivables account (code '1020', ID 5)
                $stmtRecAcc = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '1020' LIMIT 1");
                $recAccountId = (int)($stmtRecAcc->fetchColumn() ?: 5);

                $stmtJV = $db->prepare("
                    INSERT INTO `journal_entries` (
                        `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`,
                        `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`
                    ) VALUES (
                        :enum, :sid, :edate, 'journal', 'cancellation',
                        :ref_id, :narration, :tdebit, :tcredit, :uid
                    )
                ");
                $stmtJV->execute([
                    'enum'      => $jvNum,
                    'sid'       => $societyId,
                    'edate'     => $cancellationDate,
                    'ref_id'    => $cancellationId,
                    'narration' => "Cancellation of Booking #{$booking['booking_number']} ({$cancellationNumber})",
                    'tdebit'    => $totalPaid,
                    'tcredit'   => $totalPaid,
                    'uid'       => $approvedByUserId
                ]);
                $jvId = (int)$db->lastInsertId();

                $stmtJLine = $db->prepare("
                    INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `memo`)
                    VALUES (:jvid, :aid, :debit, :credit, :memo)
                ");

                // Line 1: Debit Receivables (for amount paid released)
                $stmtJLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $recAccountId,
                    'debit'  => $totalPaid,
                    'credit' => 0.00,
                    'memo'   => "Reversal of booking receipts upon cancellation ({$cancellationNumber})"
                ]);

                // Line 2: Credit Refund Payable (if any refund is due to customer)
                if ($refundPayable > 0) {
                    $stmtJLine->execute([
                        'jvid'   => $jvId,
                        'aid'    => $refundAccountId,
                        'debit'  => 0.00,
                        'credit' => $refundPayable,
                        'memo'   => "Net refund liability payable to customer ({$cancellationNumber})"
                    ]);
                    $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                       ->execute(['amt' => $refundPayable, 'aid' => $refundAccountId]);
                }

                // Line 3: Credit Cancellation Charges Income (for deduction retained)
                if ($deductionAmount > 0) {
                    $stmtJLine->execute([
                        'jvid'   => $jvId,
                        'aid'    => $incomeAccountId,
                        'debit'  => 0.00,
                        'credit' => $deductionAmount,
                        'memo'   => "Cancellation forfeiture income retained by society ({$cancellationNumber})"
                    ]);
                    $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                       ->execute(['amt' => $deductionAmount, 'aid' => $incomeAccountId]);
                }
            }

            $db->commit();

            log_audit('PLOT_CANCELLED', 'cancellations', $cancellationId, null, [
                'cancellation_number'   => $cancellationNumber,
                'booking_id'            => $bookingId,
                'plot_id'               => $plotId,
                'total_paid'            => $totalPaid,
                'deduction_amount'      => $deductionAmount,
                'refund_amount_payable' => $refundPayable
            ], $societyId);

            return $cancellationId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
