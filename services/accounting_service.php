<?php
/**
 * HSMS - Double-Entry Accounting Service
 * Guarantees strict mathematical balance (Debit == Credit),
 * Chart of Accounts balance updates, and GAAP compliant General Ledger posting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

class AccountingService {
    /**
     * Generate sequential unique Journal Voucher number
     */
    public static function generateJVNumber(PDO $db): string {
        $year = date('Y');
        $stmt = $db->prepare("SELECT COALESCE(MAX(id), 0) + 1 FROM `journal_entries`");
        $stmt->execute();
        $nextId = (int)$stmt->fetchColumn();

        return sprintf('JV-%s-%04d', $year, $nextId);
    }

    /**
     * Generate sequential Expense Voucher number
     */
    public static function generateExpenseVoucherNumber(PDO $db, int $societyId): string {
        $year = date('Y');
        $stmtSoc = $db->prepare("SELECT society_code FROM `societies` WHERE `id` = :sid LIMIT 1");
        $stmtSoc->execute(['sid' => $societyId]);
        $socCode = $stmtSoc->fetchColumn() ?: 'SOC';

        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM `expenses` WHERE `society_id` = :sid AND YEAR(`expense_date`) = :yr");
        $stmtCnt->execute(['sid' => $societyId, 'yr' => $year]);
        $nextNum = ((int)$stmtCnt->fetchColumn()) + 1;

        return sprintf('EXP-%s-%s-%04d', $socCode, $year, $nextNum);
    }

    /**
     * Post a Journal Entry with strict double-entry balance verification
     * 
     * @param array $lines Array of ['account_id' => int, 'debit' => float, 'credit' => float, 'memo' => ?string]
     */
    public static function postJournalEntry(
        int $societyId,
        string $entryDate,
        string $entryType, // 'journal', 'receipt', 'payment', 'contra'
        ?string $referenceType,
        ?int $referenceId,
        string $narration,
        array $lines,
        int $createdByUserId = 1
    ): int {
        $db = get_db_connection();

        if (count($lines) < 2) {
            throw new InvalidArgumentException("A journal entry requires at least two lines (Debit and Credit).");
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $validatedLines = [];

        foreach ($lines as $idx => $line) {
            $accId = (int)($line['account_id'] ?? 0);
            $debit = round((float)($line['debit'] ?? 0), 2);
            $credit = round((float)($line['credit'] ?? 0), 2);
            $memo = trim($line['memo'] ?? '');

            if ($accId <= 0) {
                throw new InvalidArgumentException("Invalid account selected on line " . ($idx + 1));
            }
            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException("Debit and credit amounts cannot be negative.");
            }
            if ($debit == 0 && $credit == 0) {
                continue; // Skip zero line
            }
            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException("A journal line cannot contain both debit and credit (Line " . ($idx + 1) . ").");
            }

            $totalDebit = round($totalDebit + $debit, 2);
            $totalCredit = round($totalCredit + $credit, 2);

            $validatedLines[] = [
                'account_id' => $accId,
                'debit'      => $debit,
                'credit'     => $credit,
                'memo'       => $memo ?: $narration
            ];
        }

        // STRICT DOUBLE-ENTRY RECONCILIATION:
        // Total Debits MUST EQUAL Total Credits exactly
        if (abs($totalDebit - $totalCredit) > 0.001 || $totalDebit <= 0) {
            throw new InvalidArgumentException(sprintf(
                "Unbalanced Journal Entry! Total Debits (Rs. %s) must equal Total Credits (Rs. %s).",
                number_format($totalDebit, 2),
                number_format($totalCredit, 2)
            ));
        }

        $isExternalTx = $db->inTransaction();
        if (!$isExternalTx) {
            $db->beginTransaction();
        }

        try {
            $jvNumber = self::generateJVNumber($db);

            $stmtJV = $db->prepare("
                INSERT INTO `journal_entries` (
                    `entry_number`, `society_id`, `entry_date`, `entry_type`, `reference_type`,
                    `reference_id`, `narration`, `total_debit`, `total_credit`, `created_by`
                ) VALUES (
                    :enum, :sid, :edate, :etype, :reftype,
                    :refid, :narration, :tdebit, :tcredit, :uid
                )
            ");
            $stmtJV->execute([
                'enum'      => $jvNumber,
                'sid'       => $societyId,
                'edate'     => $entryDate,
                'etype'     => $entryType,
                'reftype'   => $referenceType ?: null,
                'refid'     => $referenceId ?: null,
                'narration' => $narration,
                'tdebit'    => $totalDebit,
                'tcredit'   => $totalCredit,
                'uid'       => $createdByUserId
            ]);
            $jvId = (int)$db->lastInsertId();

            $stmtLine = $db->prepare("
                INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `memo`)
                VALUES (:jvid, :aid, :debit, :credit, :memo)
            ");

            // Fetch accounts for balance effect calculation
            $stmtAccInfo = $db->prepare("SELECT account_type FROM `accounts` WHERE `id` = :aid FOR UPDATE");

            foreach ($validatedLines as $vLine) {
                $stmtLine->execute([
                    'jvid'   => $jvId,
                    'aid'    => $vLine['account_id'],
                    'debit'  => $vLine['debit'],
                    'credit' => $vLine['credit'],
                    'memo'   => $vLine['memo']
                ]);

                // Update Account Current Balance according to standard accounting equation
                $stmtAccInfo->execute(['aid' => $vLine['account_id']]);
                $accType = $stmtAccInfo->fetchColumn();

                // Assets & Expenses: Normal balance is DEBIT
                // Liabilities, Equity & Income: Normal balance is CREDIT
                if (in_array($accType, ['asset', 'expense'], true)) {
                    $delta = $vLine['debit'] - $vLine['credit'];
                } else {
                    $delta = $vLine['credit'] - $vLine['debit'];
                }

                $db->prepare("UPDATE `accounts` SET `current_balance` = `current_balance` + :delta WHERE `id` = :aid")
                   ->execute(['delta' => $delta, 'aid' => $vLine['account_id']]);
            }

            if (!$isExternalTx) {
                $db->commit();
            }

            log_audit('JOURNAL_ENTRY_POSTED', 'journal_entries', $jvId, null, [
                'entry_number' => $jvNumber,
                'total_amount' => $totalDebit,
                'lines_count'  => count($validatedLines)
            ], $societyId);

            return $jvId;

        } catch (Throwable $e) {
            if (!$isExternalTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Post Expense Voucher and auto-generate balanced Double-Entry Journal Entry
     */
    public static function postExpenseVoucher(
        int $societyId,
        int $expenseCategoryId,
        int $paymentAccountId,
        float $amount,
        string $paidTo,
        ?string $referenceNo = null,
        ?string $description = null,
        ?string $attachmentPath = null,
        ?string $expenseDate = null,
        int $createdByUserId = 1
    ): int {
        $db = get_db_connection();
        $expenseDate = $expenseDate ?: date('Y-m-d');
        $amount = round($amount, 2);
        $paidTo = trim($paidTo);

        if ($amount <= 0) {
            throw new InvalidArgumentException("Expense amount must be greater than zero.");
        }
        if (empty($paidTo)) {
            throw new InvalidArgumentException("Payee name ('Paid To') is required.");
        }

        $db->beginTransaction();
        try {
            // 1. Fetch category and linked account
            $stmtCat = $db->prepare("SELECT * FROM `expense_categories` WHERE `id` = :cid AND `status` = 'active'");
            $stmtCat->execute(['cid' => $expenseCategoryId]);
            $category = $stmtCat->fetch();

            if (!$category) {
                throw new RuntimeException("Selected expense category is invalid or inactive.");
            }

            $expenseAccountId = (int)($category['account_id'] ?: 18); // Default Office Utilities/Expenses if not linked

            // 2. Fetch payment account
            $stmtPayAcc = $db->prepare("SELECT * FROM `accounts` WHERE `id` = :aid AND `account_type` = 'asset' FOR UPDATE");
            $stmtPayAcc->execute(['aid' => $paymentAccountId]);
            $payAcc = $stmtPayAcc->fetch();

            if (!$payAcc) {
                throw new RuntimeException("Selected cash or bank payment account is invalid.");
            }

            // 3. Generate Voucher Number
            $voucherNumber = self::generateExpenseVoucherNumber($db, $societyId);

            // 4. Insert into expenses table
            $stmtInsExp = $db->prepare("
                INSERT INTO `expenses` (
                    `voucher_number`, `society_id`, `expense_category_id`, `payment_account_id`,
                    `expense_date`, `amount`, `paid_to`, `reference_no`, `description`,
                    `attachment_path`, `approval_status`, `created_by`
                ) VALUES (
                    :vnum, :sid, :cat_id, :acc_id,
                    :edate, :amt, :paid_to, :ref, :desc,
                    :attach, 'approved', :uid
                )
            ");
            $stmtInsExp->execute([
                'vnum'    => $voucherNumber,
                'sid'     => $societyId,
                'cat_id'  => $expenseCategoryId,
                'acc_id'  => $paymentAccountId,
                'edate'   => $expenseDate,
                'amt'     => $amount,
                'paid_to' => $paidTo,
                'ref'     => $referenceNo ?: null,
                'desc'    => $description ?: null,
                'attach'  => $attachmentPath ?: null,
                'uid'     => $createdByUserId
            ]);
            $expenseId = (int)$db->lastInsertId();

            // 5. Post Double-Entry Journal Entry
            // Debit: Expense Account (increase expense)
            // Credit: Cash/Bank Account (decrease asset)
            $jvLines = [
                [
                    'account_id' => $expenseAccountId,
                    'debit'      => $amount,
                    'credit'     => 0.00,
                    'memo'       => "Expense: {$category['name']} (Paid to: {$paidTo})"
                ],
                [
                    'account_id' => $paymentAccountId,
                    'debit'      => 0.00,
                    'credit'     => $amount,
                    'memo'       => "Payment for voucher {$voucherNumber}"
                ]
            ];

            self::postJournalEntry(
                $societyId,
                $expenseDate,
                'payment',
                'expense',
                $expenseId,
                "Expense Voucher {$voucherNumber} — {$paidTo} ({$category['name']})",
                $jvLines,
                $createdByUserId
            );

            $db->commit();

            log_audit('EXPENSE_POSTED', 'expenses', $expenseId, null, [
                'voucher_number' => $voucherNumber,
                'amount'         => $amount,
                'paid_to'        => $paidTo,
                'category'       => $category['name']
            ], $societyId);

            return $expenseId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Calculate Society Trial Balance as of a specified date
     */
    public static function getTrialBalance(int $societyId, ?string $asOfDate = null): array {
        $db = get_db_connection();
        $dateCondition = $asOfDate ? "AND je.entry_date <= :as_of" : "";

        $sql = "
            SELECT a.id, a.account_code, a.account_name, a.account_type, a.sub_type, a.opening_balance,
                   COALESCE(SUM(jel.debit), 0.00) as total_debit,
                   COALESCE(SUM(jel.credit), 0.00) as total_credit
            FROM `accounts` a
            LEFT JOIN `journal_entry_lines` jel ON a.id = jel.account_id
            LEFT JOIN `journal_entries` je ON jel.journal_entry_id = je.id AND je.society_id = :sid {$dateCondition}
            WHERE a.status = 'active'
            GROUP BY a.id, a.account_code, a.account_name, a.account_type, a.sub_type, a.opening_balance
            ORDER BY a.account_code ASC
        ";

        $params = ['sid' => $societyId];
        if ($asOfDate) {
            $params['as_of'] = $asOfDate;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rawAccounts = $stmt->fetchAll();

        $rows = [];
        $sumDebit = 0.0;
        $sumCredit = 0.0;

        foreach ($rawAccounts as $acc) {
            $debits = (float)$acc['total_debit'];
            $credits = (float)$acc['total_credit'];
            $type = $acc['account_type'];

            // Calculate Net Trial Balance per account
            // Assets & Expenses: Net Debit = Debits - Credits
            // Liabilities, Equity & Income: Net Credit = Credits - Debits
            $netDebit = 0.0;
            $netCredit = 0.0;

            if (in_array($type, ['asset', 'expense'], true)) {
                $net = round($debits - $credits, 2);
                if ($net >= 0) {
                    $netDebit = $net;
                } else {
                    $netCredit = abs($net);
                }
            } else {
                $net = round($credits - $debits, 2);
                if ($net >= 0) {
                    $netCredit = $net;
                } else {
                    $netDebit = abs($net);
                }
            }

            if ($netDebit > 0 || $netCredit > 0 || (float)$acc['opening_balance'] != 0) {
                $rows[] = [
                    'account_id'   => (int)$acc['id'],
                    'account_code' => $acc['account_code'],
                    'account_name' => $acc['account_name'],
                    'account_type' => $acc['account_type'],
                    'sub_type'     => $acc['sub_type'],
                    'debit'        => $netDebit,
                    'credit'       => $netCredit
                ];

                $sumDebit = round($sumDebit + $netDebit, 2);
                $sumCredit = round($sumCredit + $netCredit, 2);
            }
        }

        return [
            'accounts'     => $rows,
            'total_debit'  => $sumDebit,
            'total_credit' => $sumCredit,
            'is_balanced'  => abs($sumDebit - $sumCredit) < 0.01,
            'difference'   => round(abs($sumDebit - $sumCredit), 2)
        ];
    }
}
