<?php
/**
 * HSMS - Payment Processing & Allocation Engine Service
 * Guarantees atomic receipt posting, FIFO / Specific allocations,
 * Customer Ledger balance tracking, and balanced Double-Entry Journal Entries.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class PaymentService {
    /**
     * Generate next sequential receipt number per society & fiscal year
     * Format: REC-{SOCIETY_CODE}-{YEAR}-{0001}
     */
    public static function generateReceiptNumber(PDO $db, int $societyId): string {
        $year = date('Y');

        // Fetch society code
        $stmtSoc = $db->prepare("SELECT society_code FROM `societies` WHERE `id` = :sid LIMIT 1");
        $stmtSoc->execute(['sid' => $societyId]);
        $socCode = $stmtSoc->fetchColumn() ?: 'SOC';

        $stmtSeq = $db->prepare("
            SELECT last_number 
            FROM `receipt_sequences` 
            WHERE `society_id` = :sid AND `fiscal_year` = :fyear 
            FOR UPDATE
        ");
        $stmtSeq->execute(['sid' => $societyId, 'fyear' => $year]);
        $lastNum = $stmtSeq->fetchColumn();

        if ($lastNum === false) {
            $nextNum = 1;
            $insSeq = $db->prepare("INSERT INTO `receipt_sequences` (`society_id`, `fiscal_year`, `last_number`) VALUES (:sid, :fyear, :num)");
            $insSeq->execute(['sid' => $societyId, 'fyear' => $year, 'num' => $nextNum]);
        } else {
            $nextNum = ((int)$lastNum) + 1;
            $updSeq = $db->prepare("UPDATE `receipt_sequences` SET `last_number` = :num WHERE `society_id` = :sid AND `fiscal_year` = :fyear");
            $updSeq->execute(['num' => $nextNum, 'sid' => $societyId, 'fyear' => $year]);
        }

        return sprintf('REC-%s-%s-%04d', $socCode, $year, $nextNum);
    }

    /**
     * Post a Payment Receipt inside an atomic database transaction
     */
    public static function postPayment(
        int $societyId,
        int $bookingId,
        int $customerId,
        float $paymentAmount,
        string $paymentDate,
        string $paymentMethod,
        int $cashBankAccountId,
        ?string $transactionRef = null,
        string $allocationMethod = 'fifo', // 'fifo' or 'specific'
        ?int $specificScheduleId = null,
        ?int $recoveryOfficerId = null,
        ?string $remarks = null,
        ?string $idempotencyKey = null,
        int $createdByUserId = 1
    ): int {
        $db = get_db_connection();
        $paymentAmount = round($paymentAmount, 2);

        if ($paymentAmount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        // Idempotency check: prevent duplicate submissions
        if (!empty($idempotencyKey)) {
            $stmtCheck = $db->prepare("SELECT id FROM `payments` WHERE `idempotency_key` = :ikey LIMIT 1");
            $stmtCheck->execute(['ikey' => $idempotencyKey]);
            $existingId = $stmtCheck->fetchColumn();
            if ($existingId) {
                return (int)$existingId;
            }
        }

        $db->beginTransaction();
        try {
            // 1. Lock and fetch Booking
            $stmtBk = $db->prepare("SELECT * FROM `bookings` WHERE `id` = :bid AND `society_id` = :sid FOR UPDATE");
            $stmtBk->execute(['bid' => $bookingId, 'sid' => $societyId]);
            $booking = $stmtBk->fetch();

            if (!$booking) {
                throw new RuntimeException("Booking #{$bookingId} not found in active society.");
            }
            if ($booking['status'] !== 'active') {
                throw new RuntimeException("Cannot post payment to non-active contract (Status: {$booking['status']}).");
            }
            if ((int)$booking['customer_id'] !== $customerId) {
                throw new RuntimeException("Customer mismatch for booking contract.");
            }

            // 2. Lock and fetch Cash/Bank Account
            $stmtAcc = $db->prepare("SELECT * FROM `accounts` WHERE `id` = :aid AND `account_type` = 'asset' FOR UPDATE");
            $stmtAcc->execute(['aid' => $cashBankAccountId]);
            $paymentAccount = $stmtAcc->fetch();

            if (!$paymentAccount) {
                throw new RuntimeException("Designated cash or bank account is invalid.");
            }

            // 3. Lock and fetch all unpaid installment schedules
            $stmtSched = $db->prepare("
                SELECT * 
                FROM `installment_schedules` 
                WHERE `booking_id` = :bid AND `balance_due` > 0
                ORDER BY `installment_no` ASC 
                FOR UPDATE
            ");
            $stmtSched->execute(['bid' => $bookingId]);
            $unpaidSchedules = $stmtSched->fetchAll();

            // 4. Perform Payment Allocations
            $allocatedTotal = 0.0;
            $allocationsPlan = [];
            $unallocatedAmount = $paymentAmount;

            if ($allocationMethod === 'specific' && $specificScheduleId > 0) {
                // Find specific installment
                foreach ($unpaidSchedules as $s) {
                    if ((int)$s['id'] === $specificScheduleId) {
                        $due = (float)$s['balance_due'];
                        $alloc = min($unallocatedAmount, $due);
                        $allocationsPlan[] = ['schedule' => $s, 'amount' => $alloc];
                        $allocatedTotal += $alloc;
                        $unallocatedAmount = round($unallocatedAmount - $alloc, 2);
                        break;
                    }
                }
            }

            // Allocate remaining amount to oldest overdue/pending schedules (FIFO)
            if ($unallocatedAmount > 0) {
                foreach ($unpaidSchedules as $s) {
                    if ($unallocatedAmount <= 0) break;
                    // Skip if already in plan from specific allocation
                    $alreadyAllocated = false;
                    foreach ($allocationsPlan as $ap) {
                        if ((int)$ap['schedule']['id'] === (int)$s['id']) {
                            $alreadyAllocated = true;
                            break;
                        }
                    }
                    if ($alreadyAllocated) continue;

                    $due = (float)$s['balance_due'];
                    $alloc = min($unallocatedAmount, $due);
                    if ($alloc > 0) {
                        $allocationsPlan[] = ['schedule' => $s, 'amount' => $alloc];
                        $allocatedTotal = round($allocatedTotal + $alloc, 2);
                        $unallocatedAmount = round($unallocatedAmount - $alloc, 2);
                    }
                }
            }

            // Any remaining payment after clearing all installments is credited as Customer Advance
            $advanceAmount = round($unallocatedAmount, 2);

            // Reconcile mathematical equality:
            // Payment Amount == Allocated Amount + Advance Amount
            if (abs(($allocatedTotal + $advanceAmount) - $paymentAmount) > 0.01) {
                throw new RuntimeException("Allocation mathematical imbalance! Allocated: {$allocatedTotal}, Advance: {$advanceAmount}, Paid: {$paymentAmount}");
            }

            // 5. Generate Receipt Number
            $receiptNumber = self::generateReceiptNumber($db, $societyId);

            // 6. Insert Payment Record
            $stmtPay = $db->prepare("
                INSERT INTO `payments` (
                    `receipt_number`, `society_id`, `booking_id`, `customer_id`,
                    `payment_date`, `payment_amount`, `allocated_amount`, `advance_amount`,
                    `payment_method`, `cash_bank_account_id`, `transaction_ref`,
                    `recovery_officer_id`, `status`, `remarks`, `idempotency_key`, `created_by`
                ) VALUES (
                    :rnum, :sid, :bid, :cid,
                    :pdate, :amount, :allocated, :advance,
                    :method, :aid, :ref,
                    :officer, 'posted', :remarks, :ikey, :uid
                )
            ");
            $stmtPay->execute([
                'rnum'      => $receiptNumber,
                'sid'       => $societyId,
                'bid'       => $bookingId,
                'cid'       => $customerId,
                'pdate'     => $paymentDate,
                'amount'    => $paymentAmount,
                'allocated' => $allocatedTotal,
                'advance'   => $advanceAmount,
                'method'    => $paymentMethod,
                'aid'       => $cashBankAccountId,
                'ref'       => $transactionRef ?: null,
                'officer'   => $recoveryOfficerId ?: null,
                'remarks'   => $remarks ?: null,
                'ikey'      => $idempotencyKey ?: null,
                'uid'       => $createdByUserId
            ]);
            $paymentId = (int)$db->lastInsertId();

            // 7. Update Schedules and record payment allocations
            $stmtInsAlloc = $db->prepare("
                INSERT INTO `payment_allocations` (`payment_id`, `schedule_id`, `allocated_amount`)
                VALUES (:pid, :sid, :amount)
            ");
            $stmtUpdSched = $db->prepare("
                UPDATE `installment_schedules`
                SET `amount_paid` = `amount_paid` + :alloc_add,
                    `balance_due` = `balance_due` - :alloc_sub,
                    `status` = :status,
                    `paid_date` = :pdate
                WHERE `id` = :sid
            ");

            foreach ($allocationsPlan as $item) {
                $sRow = $item['schedule'];
                $alloc = $item['amount'];

                $stmtInsAlloc->execute([
                    'pid'    => $paymentId,
                    'sid'    => $sRow['id'],
                    'amount' => $alloc
                ]);

                $newBalance = round((float)$sRow['balance_due'] - $alloc, 2);
                $newStatus = ($newBalance <= 0) ? 'paid' : 'partially_paid';

                $stmtUpdSched->execute([
                    'alloc_add' => $alloc,
                    'alloc_sub' => $alloc,
                    'status'    => $newStatus,
                    'pdate'     => ($newStatus === 'paid') ? $paymentDate : $sRow['paid_date'],
                    'sid'       => $sRow['id']
                ]);
            }

            // 8. Update Booking Balances
            $newTotalPaid = round((float)$booking['total_paid'] + $allocatedTotal, 2);
            $newRemainingBal = round((float)$booking['remaining_balance'] - $allocatedTotal, 2);
            $newAdvanceBal = round((float)$booking['advance_balance'] + $advanceAmount, 2);

            // Check if down payment is now fully paid
            $dpStatus = $booking['down_payment_status'];
            $stmtCheckDp = $db->prepare("SELECT balance_due FROM `installment_schedules` WHERE `booking_id` = :bid AND `installment_no` = 0 LIMIT 1");
            $stmtCheckDp->execute(['bid' => $bookingId]);
            $dpDue = $stmtCheckDp->fetchColumn();
            if ($dpDue !== false && (float)$dpDue <= 0) {
                $dpStatus = 'paid';
            }

            $stmtUpdBk = $db->prepare("
                UPDATE `bookings` SET
                    `total_paid` = :tp,
                    `remaining_balance` = :rem,
                    `advance_balance` = :adv,
                    `down_payment_status` = :dp_stat
                WHERE `id` = :bid
            ");
            $stmtUpdBk->execute([
                'tp'      => $newTotalPaid,
                'rem'     => $newRemainingBal,
                'adv'     => $newAdvanceBal,
                'dp_stat' => $dpStatus,
                'bid'     => $bookingId
            ]);

            // 9. Post Customer Ledger Entries
            // Calculate previous running balance
            $stmtPrevLedger = $db->prepare("
                SELECT running_balance 
                FROM `customer_ledger` 
                WHERE `customer_id` = :cid AND `booking_id` = :bid 
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmtPrevLedger->execute(['cid' => $customerId, 'bid' => $bookingId]);
            $prevBal = (float)($stmtPrevLedger->fetchColumn() ?: $booking['net_sale_price']);

            // Post payment credit against dues
            if ($allocatedTotal > 0) {
                $newBal = round($prevBal - $allocatedTotal, 2);
                $stmtLedger = $db->prepare("
                    INSERT INTO `customer_ledger` (
                        `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                        `transaction_type`, `reference_id`, `voucher_no`, `description`,
                        `debit`, `credit`, `running_balance`
                    ) VALUES (
                        :sid, :cid, :bid, :pid, :tdate,
                        'payment_credit', :ref_id, :vnum, :desc,
                        0.00, :credit, :bal
                    )
                ");
                $stmtLedger->execute([
                    'sid'    => $societyId,
                    'cid'    => $customerId,
                    'bid'    => $bookingId,
                    'pid'    => $booking['plot_id'],
                    'tdate'  => $paymentDate,
                    'ref_id' => $paymentId,
                    'vnum'   => $receiptNumber,
                    'desc'   => "Payment Receipt {$receiptNumber} ({$paymentMethod})",
                    'credit' => $allocatedTotal,
                    'bal'    => $newBal
                ]);
                $prevBal = $newBal;
            }

            // If excess payment received, record advance credit
            if ($advanceAmount > 0) {
                $newBal = round($prevBal - $advanceAmount, 2);
                $stmtAdvLedger = $db->prepare("
                    INSERT INTO `customer_ledger` (
                        `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                        `transaction_type`, `reference_id`, `voucher_no`, `description`,
                        `debit`, `credit`, `running_balance`
                    ) VALUES (
                        :sid, :cid, :bid, :pid, :tdate,
                        'advance_credit', :ref_id, :vnum, :desc,
                        0.00, :credit, :bal
                    )
                ");
                $stmtAdvLedger->execute([
                    'sid'    => $societyId,
                    'cid'    => $customerId,
                    'bid'    => $bookingId,
                    'pid'    => $booking['plot_id'],
                    'tdate'  => $paymentDate,
                    'ref_id' => $paymentId,
                    'vnum'   => $receiptNumber,
                    'desc'   => "Customer Advance Credit on {$receiptNumber}",
                    'credit' => $advanceAmount,
                    'bal'    => $newBal
                ]);
            }

            // 10. Post Double-Entry Accounting Journal Entry
            // Rule: Debit Cash/Bank (Total Paid), Credit Customer Receivables (Allocated), Credit Customer Advance (Advance)
            $stmtJVNum = $db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM `journal_entries`");
            $stmtJVNum->execute();
            $jvEntryNum = sprintf('JV-%s-%04d', date('Y'), (int)$stmtJVNum->fetchColumn());

            // Find system receivable account (account_code '1020') and advance liability account ('2001')
            $stmtAccRec = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '1020' LIMIT 1");
            $recAccountId = (int)($stmtAccRec->fetchColumn() ?: 5);

            $stmtAccAdv = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '2001' LIMIT 1");
            $advAccountId = (int)($stmtAccAdv->fetchColumn() ?: 6);

            $stmtJV = $db->prepare("
                INSERT INTO `journal_entries` (
                    `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`,
                    `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`
                ) VALUES (
                    :enum, :sid, :edate, 'receipt', 'payment',
                    :ref_id, :narration, :tdebit, :tcredit, :uid
                )
            ");
            $stmtJV->execute([
                'enum'      => $jvEntryNum,
                'sid'       => $societyId,
                'edate'     => $paymentDate,
                'ref_id'    => $paymentId,
                'narration' => "Receipt {$receiptNumber} for Booking #{$booking['booking_number']}",
                'tdebit'    => $paymentAmount,
                'tcredit'   => $paymentAmount,
                'uid'       => $createdByUserId
            ]);
            $jvId = (int)$db->lastInsertId();

            // Line 1: Debit Cash/Bank
            $stmtJLine = $db->prepare("
                INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `memo`)
                VALUES (:jvid, :aid, :debit, :credit, :memo)
            ");
            $stmtJLine->execute([
                'jvid'   => $jvId,
                'aid'    => $cashBankAccountId,
                'debit'  => $paymentAmount,
                'credit' => 0.00,
                'memo'   => "Payment received via {$paymentMethod}"
            ]);

            // Line 2: Credit Receivables (Allocated amount)
            if ($allocatedTotal > 0) {
                $stmtJLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $recAccountId,
                    'debit'  => 0.00,
                    'credit' => $allocatedTotal,
                    'memo'   => "Installment allocation on {$receiptNumber}"
                ]);
            }

            // Line 3: Credit Advance Liability (Advance amount if any)
            if ($advanceAmount > 0) {
                $stmtJLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $advAccountId,
                    'debit'  => 0.00,
                    'credit' => $advanceAmount,
                    'memo'   => "Unallocated advance customer credit on {$receiptNumber}"
                ]);
            }

            // Update Account Current Balances
            $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
               ->execute(['amt' => $paymentAmount, 'aid' => $cashBankAccountId]);
            $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` - :amt WHERE `id` = :aid")
               ->execute(['amt' => $allocatedTotal, 'aid' => $recAccountId]);
            if ($advanceAmount > 0) {
                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                   ->execute(['amt' => $advanceAmount, 'aid' => $advAccountId]);
            }

            // Commit Transaction
            $db->commit();

            log_audit('PAYMENT_POSTED', 'recoveries', $paymentId, null, [
                'receipt_number' => $receiptNumber,
                'amount'         => $paymentAmount,
                'allocated'      => $allocatedTotal,
                'advance'        => $advanceAmount,
                'booking_id'     => $bookingId
            ], $societyId);

            return $paymentId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reverse a mistaken payment transaction with audit trail
     */
    public static function reversePayment(int $paymentId, string $reason, int $reversedByUserId): void {
        $db = get_db_connection();

        $db->beginTransaction();
        try {
            $stmtPay = $db->prepare("SELECT * FROM `payments` WHERE `id` = :pid FOR UPDATE");
            $stmtPay->execute(['pid' => $paymentId]);
            $payment = $stmtPay->fetch();

            if (!$payment) {
                throw new RuntimeException("Payment #{$paymentId} not found.");
            }
            if ($payment['status'] === 'reversed') {
                throw new RuntimeException("This payment receipt has already been reversed.");
            }

            // 1. Rollback installment allocations
            $stmtAlloc = $db->prepare("SELECT * FROM `payment_allocations` WHERE `payment_id` = :pid");
            $stmtAlloc->execute(['pid' => $paymentId]);
            $allocations = $stmtAlloc->fetchAll();

            $stmtRollSched = $db->prepare("
                UPDATE `installment_schedules`
                SET `amount_paid` = `amount_paid` - :alloc_sub,
                    `balance_due` = `balance_due` + :alloc_add,
                    `status` = CASE WHEN (`amount_paid` - :alloc_check) <= 0 THEN 'pending' ELSE 'partially_paid' END,
                    `paid_date` = NULL
                WHERE `id` = :sid
            ");

            foreach ($allocations as $al) {
                $stmtRollSched->execute([
                    'alloc_sub'   => $al['allocated_amount'],
                    'alloc_add'   => $al['allocated_amount'],
                    'alloc_check' => $al['allocated_amount'],
                    'sid'         => $al['schedule_id']
                ]);
            }

            // 2. Rollback booking totals
            $stmtBk = $db->prepare("
                UPDATE `bookings`
                SET `total_paid` = `total_paid` - :allocated_sub,
                    `remaining_balance` = `remaining_balance` + :allocated_add,
                    `advance_balance` = `advance_balance` - :advance
                WHERE `id` = :bid
            ");
            $stmtBk->execute([
                'allocated_sub' => $payment['allocated_amount'],
                'allocated_add' => $payment['allocated_amount'],
                'advance'       => $payment['advance_amount'],
                'bid'           => $payment['booking_id']
            ]);

            // 3. Post reversal on customer ledger
            $stmtPlot = $db->prepare("SELECT plot_id FROM `bookings` WHERE `id` = :bid LIMIT 1");
            $stmtPlot->execute(['bid' => $payment['booking_id']]);
            $plotId = $stmtPlot->fetchColumn();

            $stmtPrevLedger = $db->prepare("
                SELECT running_balance 
                FROM `customer_ledger` 
                WHERE `customer_id` = :cid AND `booking_id` = :bid 
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmtPrevLedger->execute(['cid' => $payment['customer_id'], 'bid' => $payment['booking_id']]);
            $prevBal = (float)$stmtPrevLedger->fetchColumn();

            $reversalDebit = (float)$payment['allocated_amount'];
            $newBal = round($prevBal + $reversalDebit, 2);

            $stmtLedger = $db->prepare("
                INSERT INTO `customer_ledger` (
                    `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                    `transaction_type`, `reference_id`, `voucher_no`, `description`,
                    `debit`, `credit`, `running_balance`
                ) VALUES (
                    :sid, :cid, :bid, :pid, CURDATE(),
                    'reversal', :ref_id, :vnum, :desc,
                    :debit, 0.00, :bal
                )
            ");
            $stmtLedger->execute([
                'sid'    => $payment['society_id'],
                'cid'    => $payment['customer_id'],
                'bid'    => $payment['booking_id'],
                'pid'    => $plotId ?: null,
                'ref_id' => $paymentId,
                'vnum'   => 'REV-' . $payment['receipt_number'],
                'desc'   => "Reversal of Receipt {$payment['receipt_number']}: {$reason}",
                'debit'  => $reversalDebit,
                'bal'    => $newBal
            ]);

            // 4. Record payment reversal row
            $stmtInsRev = $db->prepare("
                INSERT INTO `payment_reversals` (`payment_id`, `reversal_reason`, `reversed_by`)
                VALUES (:pid, :reason, :uid)
            ");
            $stmtInsRev->execute(['pid' => $paymentId, 'reason' => $reason, 'uid' => $reversedByUserId]);

            // 5. Update payment status to 'reversed'
            $db->prepare("UPDATE `payments` SET `status` = 'reversed' WHERE `id` = :pid")
               ->execute(['pid' => $paymentId]);

            // 6. Rollback account balances
            $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` - :amt WHERE `id` = :aid")
               ->execute(['amt' => $payment['payment_amount'], 'aid' => $payment['cash_bank_account_id']]);

            // 7. Post Reversal Journal Entry for balanced books
            $stmtJVNum = $db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM `journal_entries`");
            $stmtJVNum->execute();
            $revJVNum = sprintf('JV-%s-%04d', date('Y'), (int)$stmtJVNum->fetchColumn());

            $stmtAccRec = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '1020' LIMIT 1");
            $recAccountId = (int)($stmtAccRec->fetchColumn() ?: 5);

            $stmtAccAdv = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '2001' LIMIT 1");
            $advAccountId = (int)($stmtAccAdv->fetchColumn() ?: 6);

            $stmtRevJV = $db->prepare("
                INSERT INTO `journal_entries` (
                    `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`,
                    `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`
                ) VALUES (
                    :enum, :sid, CURDATE(), 'journal', 'payment_reversal',
                    :ref_id, :narration, :tdebit, :tcredit, :uid
                )
            ");
            $stmtRevJV->execute([
                'enum'      => $revJVNum,
                'sid'       => $payment['society_id'],
                'ref_id'    => $paymentId,
                'narration' => "Reversal of Receipt {$payment['receipt_number']}: {$reason}",
                'tdebit'    => $payment['payment_amount'],
                'tcredit'   => $payment['payment_amount'],
                'uid'       => $reversedByUserId
            ]);
            $revJvId = (int)$db->lastInsertId();

            $stmtJLine = $db->prepare("
                INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `memo`)
                VALUES (:jvid, :aid, :debit, :credit, :memo)
            ");
            // Debit Receivables
            if ((float)$payment['allocated_amount'] > 0) {
                $stmtJLine->execute([
                    'jvid'   => $revJvId,
                    'aid'    => $recAccountId,
                    'debit'  => $payment['allocated_amount'],
                    'credit' => 0.00,
                    'memo'   => "Reversal of allocation for {$payment['receipt_number']}"
                ]);
                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                   ->execute(['amt' => $payment['allocated_amount'], 'aid' => $recAccountId]);
            }
            // Debit Advance Liability
            if ((float)$payment['advance_amount'] > 0) {
                $stmtJLine->execute([
                    'jvid'   => $revJvId,
                    'aid'    => $advAccountId,
                    'debit'  => $payment['advance_amount'],
                    'credit' => 0.00,
                    'memo'   => "Reversal of advance for {$payment['receipt_number']}"
                ]);
                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` - :amt WHERE `id` = :aid")
                   ->execute(['amt' => $payment['advance_amount'], 'aid' => $advAccountId]);
            }
            // Credit Cash/Bank
            $stmtJLine->execute([
                'jvid'   => $revJvId,
                'aid'    => $payment['cash_bank_account_id'],
                'debit'  => 0.00,
                'credit' => $payment['payment_amount'],
                'memo'   => "Cash/bank outflow for reversal of {$payment['receipt_number']}"
            ]);

            $db->commit();
            log_audit('PAYMENT_REVERSED', 'recoveries', $paymentId, null, ['reason' => $reason]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
