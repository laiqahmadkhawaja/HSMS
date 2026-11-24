<?php
/**
 * HSMS - AJAX / Partial: Get Journal Entry Lines
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('accounts_manage');

$db = get_db_connection();
$activeSocId = get_active_society_id();
$jvId = (int)($_GET['id'] ?? 0);

$stmtLines = $db->prepare("
    SELECT jel.*, a.account_code, a.account_name, a.account_type
    FROM `journal_entry_lines` jel
    JOIN `accounts` a ON jel.account_id = a.id
    JOIN `journal_entries` je ON jel.journal_entry_id = je.id
    WHERE jel.journal_entry_id = :jvid AND je.society_id = :sid
    ORDER BY jel.id ASC
");
$stmtLines->execute(['jvid' => $jvId, 'sid' => $activeSocId]);
$lines = $stmtLines->fetchAll();

if (empty($lines)): ?>
    <div class="text-center py-4 text-muted">No journal entry lines found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Account Code</th>
                    <th>Account Title</th>
                    <th>Memo / Description</th>
                    <th class="text-end">Debit (Rs.)</th>
                    <th class="text-end">Credit (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totDeb = 0.0;
                $totCrd = 0.0;
                foreach ($lines as $l):
                    $totDeb += (float)$l['debit'];
                    $totCrd += (float)$l['credit'];
                ?>
                    <tr>
                        <td class="font-monospace text-primary"><?= e($l['account_code']) ?></td>
                        <td class="fw-semibold">
                            <?= e($l['account_name']) ?>
                            <span class="badge bg-light text-muted border" style="font-size: 0.65rem;"><?= ucfirst($l['account_type']) ?></span>
                        </td>
                        <td class="small text-muted"><?= e($l['memo']) ?></td>
                        <td class="text-end fw-semibold <?= ((float)$l['debit'] > 0) ? 'text-primary' : 'text-muted' ?>">
                            <?= ((float)$l['debit'] > 0) ? format_currency($l['debit']) : '-' ?>
                        </td>
                        <td class="text-end fw-semibold <?= ((float)$l['credit'] > 0) ? 'text-success' : 'text-muted' ?>">
                            <?= ((float)$l['credit'] > 0) ? format_currency($l['credit']) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="3" class="text-end">Total Sum:</td>
                    <td class="text-end text-primary"><?= format_currency($totDeb) ?></td>
                    <td class="text-end text-success"><?= format_currency($totCrd) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>
