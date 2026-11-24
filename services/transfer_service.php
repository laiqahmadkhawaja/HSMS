<?php
/**
 * HSMS - Plot Ownership Transfer Service
 * Manages atomic plot transfers, buyer/seller ledger reconciliations,
 * transfer fee collection, and double-entry journal entries.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class TransferService {
    /**
     * Generate sequential unique transfer number
     */
    public static function generateTransferNumber(PDO $db, int $societyId): string {
        $year = date('Y');

        $stmtSoc = $db->prepare("SELECT society_code FROM `societies` WHERE `id` = :sid LIMIT 1");
        $stmtSoc->execute(['sid' => $societyId]);
        $socCode = $stmtSoc->fetchColumn() ?: 'SOC';

        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM `plot_transfers` WHERE `society_id` = :sid AND YEAR(`created_at`) = :yr");
        $stmtCnt->execute(['sid' => $societyId, 'yr' => $year]);
        $nextNum = ((int)$stmtCnt->fetchColumn()) + 1;

        return sprintf('TRF-%s-%s-%04d', $socCode, $year, $nextNum);
    }

    /**
     * Execute atomic plot ownership transfer
     */
    public static function processTransfer(
        int $societyId,
        int $bookingId,
        int $toCustomerId,
        float $transferFee,
        string $transferFeeStatus = 'paid', // 'paid', 'waived', 'pending'
        ?int $feeReceivingAccountId = null,
        ?string $transferDate = null,
        ?string $remarks = null,
        int $approvedByUserId = 1
    ): int {
        $db = get_db_connection();
        $transferDate = $transferDate ?: date('Y-m-d');
        $transferFee = round($transferFee, 2);

        if ($transferFee < 0) {
            throw new InvalidArgumentException("Transfer fee cannot be negative.");
        }

        $db->beginTransaction();
        try {
            // 1. Lock Booking
            $stmtBk = $db->prepare("
                SELECT bk.*, p.plot_number, b.block_name 
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
                throw new RuntimeException("Only active booking contracts can be transferred (Current status: {$booking['status']}).");
            }

            $fromCustomerId = (int)$booking['customer_id'];
            if ($fromCustomerId === $toCustomerId) {
                throw new InvalidArgumentException("Seller and buyer cannot be the same customer.");
            }

            // 2. Lock & Verify Buyer Customer
            $stmtBuyer = $db->prepare("SELECT * FROM `customers` WHERE `id` = :cid FOR UPDATE");
            $stmtBuyer->execute(['cid' => $toCustomerId]);
            $buyer = $stmtBuyer->fetch();

            if (!$buyer || $buyer['status'] !== 'active') {
                throw new RuntimeException("Target buyer is invalid, inactive or blacklisted.");
            }

            // 3. Lock & Verify Seller Customer
            $stmtSeller = $db->prepare("SELECT * FROM `customers` WHERE `id` = :cid FOR UPDATE");
            $stmtSeller->execute(['cid' => $fromCustomerId]);
            $seller = $stmtSeller->fetch();

            // 4. Validate Receiving Account if transfer fee is paid
            if ($transferFee > 0 && $transferFeeStatus === 'paid') {
                if (!$feeReceivingAccountId || $feeReceivingAccountId <= 0) {
                    throw new InvalidArgumentException("Please select a receiving cash or bank account for the transfer fee.");
                }
                $stmtAcc = $db->prepare("SELECT * FROM `accounts` WHERE `id` = :aid AND `account_type` = 'asset' FOR UPDATE");
                $stmtAcc->execute(['aid' => $feeReceivingAccountId]);
                $acc = $stmtAcc->fetch();
                if (!$acc) {
                    throw new RuntimeException("Selected receiving account is invalid.");
                }
            }

            // 5. Generate Transfer Number
            $transferNumber = self::generateTransferNumber($db, $societyId);
            $pendingBalance = (float)$booking['remaining_balance'];

            // 6. Record in plot_transfers
            $stmtInsTrf = $db->prepare("
                INSERT INTO `plot_transfers` (
                    `transfer_number`, `society_id`, `plot_id`, `booking_id`,
                    `from_customer_id`, `to_customer_id`, `transfer_date`,
                    `transfer_fee`, `transfer_fee_status`, `pending_balance_transferred`,
                    `approval_status`, `approved_by`, `remarks`
                ) VALUES (
                    :tnum, :sid, :pid, :bid,
                    :from_cid, :to_cid, :tdate,
                    :fee, :fee_stat, :pending_bal,
                    'approved', :uid, :remarks
                )
            ");
            $stmtInsTrf->execute([
                'tnum'        => $transferNumber,
                'sid'         => $societyId,
                'pid'         => $booking['plot_id'],
                'bid'         => $bookingId,
                'from_cid'    => $fromCustomerId,
                'to_cid'      => $toCustomerId,
                'tdate'       => $transferDate,
                'fee'         => $transferFee,
                'fee_stat'    => $transferFeeStatus,
                'pending_bal' => $pendingBalance,
                'uid'         => $approvedByUserId,
                'remarks'     => $remarks ?: null
            ]);
            $transferId = (int)$db->lastInsertId();

            // 7. Update Booking Owner to new buyer
            $stmtUpdBk = $db->prepare("
                UPDATE `bookings`
                SET `customer_id` = :new_cid,
                    `remarks` = CONCAT(COALESCE(`remarks`, ''), ' | Transferred from ', :seller_name, ' on ', :tdate, ' (Ref: ', :tnum, ')')
                WHERE `id` = :bid
            ");
            $stmtUpdBk->execute([
                'new_cid'     => $toCustomerId,
                'seller_name' => $seller['full_name'],
                'tdate'       => $transferDate,
                'tnum'        => $transferNumber,
                'bid'         => $bookingId
            ]);

            // 8. Customer Ledger Entries
            // A. Seller ledger: Relinquish plot balance
            $stmtPrevSeller = $db->prepare("
                SELECT running_balance 
                FROM `customer_ledger` 
                WHERE `customer_id` = :cid AND `booking_id` = :bid 
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmtPrevSeller->execute(['cid' => $fromCustomerId, 'bid' => $bookingId]);
            $sellerPrevBal = (float)($stmtPrevSeller->fetchColumn() ?: $pendingBalance);

            $stmtSellerLedger = $db->prepare("
                INSERT INTO `customer_ledger` (
                    `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                    `transaction_type`, `reference_id`, `voucher_no`, `description`,
                    `debit`, `credit`, `running_balance`
                ) VALUES (
                    :sid, :cid, :bid, :pid, :tdate,
                    'transfer_credit', :ref_id, :vnum, :desc,
                    0.00, :credit, 0.00
                )
            ");
            $stmtSellerLedger->execute([
                'sid'    => $societyId,
                'cid'    => $fromCustomerId,
                'bid'    => $bookingId,
                'pid'    => $booking['plot_id'],
                'tdate'  => $transferDate,
                'ref_id' => $transferId,
                'vnum'   => $transferNumber,
                'desc'   => "Plot #{$booking['plot_number']} transferred to {$buyer['full_name']} ({$transferNumber})",
                'credit' => $sellerPrevBal
            ]);

            // B. Buyer ledger: Assume transferred plot balance
            $stmtPrevBuyer = $db->prepare("
                SELECT running_balance 
                FROM `customer_ledger` 
                WHERE `customer_id` = :cid 
                ORDER BY `id` DESC LIMIT 1
            ");
            $stmtPrevBuyer->execute(['cid' => $toCustomerId]);
            $buyerPrevBal = (float)($stmtPrevBuyer->fetchColumn() ?: 0.00);
            $buyerNewBal = round($buyerPrevBal + $pendingBalance, 2);

            $stmtBuyerLedger = $db->prepare("
                INSERT INTO `customer_ledger` (
                    `society_id`, `customer_id`, `booking_id`, `plot_id`, `transaction_date`,
                    `transaction_type`, `reference_id`, `voucher_no`, `description`,
                    `debit`, `credit`, `running_balance`
                ) VALUES (
                    :sid, :cid, :bid, :pid, :tdate,
                    'transfer_debit', :ref_id, :vnum, :desc,
                    :debit, 0.00, :bal
                )
            ");
            $stmtBuyerLedger->execute([
                'sid'    => $societyId,
                'cid'    => $toCustomerId,
                'bid'    => $bookingId,
                'pid'    => $booking['plot_id'],
                'tdate'  => $transferDate,
                'ref_id' => $transferId,
                'vnum'   => $transferNumber,
                'desc'   => "Plot #{$booking['plot_number']} acquired from {$seller['full_name']} ({$transferNumber})",
                'debit'  => $pendingBalance,
                'bal'    => $buyerNewBal
            ]);

            // 9. Accounting Journal Entry for Transfer Fee
            if ($transferFee > 0 && $transferFeeStatus === 'paid' && $feeReceivingAccountId) {
                $stmtJVNum = $db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM `journal_entries`");
                $stmtJVNum->execute();
                $jvNum = sprintf('JV-%s-%04d', date('Y'), (int)$stmtJVNum->fetchColumn());

                // Find Transfer Fee Income account (code '4003', ID 12)
                $stmtIncomeAcc = $db->query("SELECT id FROM `accounts` WHERE `account_code` = '4003' LIMIT 1");
                $incomeAccountId = (int)($stmtIncomeAcc->fetchColumn() ?: 12);

                $stmtJV = $db->prepare("
                    INSERT INTO `journal_entries` (
                        `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`,
                        `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`
                    ) VALUES (
                        :enum, :sid, :edate, 'receipt', 'transfer',
                        :ref_id, :narration, :tdebit, :tcredit, :uid
                    )
                ");
                $stmtJV->execute([
                    'enum'      => $jvNum,
                    'sid'       => $societyId,
                    'edate'     => $transferDate,
                    'ref_id'    => $transferId,
                    'narration' => "Transfer fee collection for {$transferNumber} (Plot #{$booking['plot_number']})",
                    'tdebit'    => $transferFee,
                    'tcredit'   => $transferFee,
                    'uid'       => $approvedByUserId
                ]);
                $jvId = (int)$db->lastInsertId();

                $stmtJLine = $db->prepare("
                    INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `memo`)
                    VALUES (:jvid, :aid, :debit, :credit, :memo)
                ");
                // Debit Cash/Bank
                $stmtJLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $feeReceivingAccountId,
                    'debit'  => $transferFee,
                    'credit' => 0.00,
                    'memo'   => "Transfer fee received ({$transferNumber})"
                ]);
                // Credit Transfer Fee Income
                $stmtJLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $incomeAccountId,
                    'debit'  => 0.00,
                    'credit' => $transferFee,
                    'memo'   => "Transfer fee revenue ({$transferNumber})"
                ]);

                // Update Account Balances
                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                   ->execute(['amt' => $transferFee, 'aid' => $feeReceivingAccountId]);
                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :amt WHERE `id` = :aid")
                   ->execute(['amt' => $transferFee, 'aid' => $incomeAccountId]);
            }

            $db->commit();

            log_audit('PLOT_TRANSFERRED', 'plot_transfers', $transferId, null, [
                'transfer_number'  => $transferNumber,
                'from_customer_id' => $fromCustomerId,
                'to_customer_id'   => $toCustomerId,
                'booking_id'       => $bookingId,
                'transfer_fee'     => $transferFee,
                'pending_balance'  => $pendingBalance
            ], $societyId);

            return $transferId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
