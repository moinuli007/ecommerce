<?php

use App\Core\View;

/** @var array<string,mixed> $report */
/** @var array<string,string> $filters */
/** @var string $appUrl */

$rows  = $report['rows'] ?? [];
$total = $report['total'] ?? ['debit' => 0, 'credit' => 0];
?>
<div class="card">
    <form class="filters" method="get">
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= View::e($filters['from']) ?>">
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= View::e($filters['to']) ?>">
        </div>
        <button type="submit">View</button>
    </form>
</div>

<div class="card">
    <?php if (!($report['is_balanced'] ?? true)): ?>
        <div class="msg msg-e">
            ⚠ Trial balance doesn't match — debit and credit totals aren't equal.
            Check whether any entries were made directly in the DB.
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="empty">No transactions in this period.</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Ledger</th>
                    <th>Code</th>
                    <th>Chart</th>
                    <th>Master</th>
                    <th class="num">Debit</th>
                    <th class="num">Credit</th>
                    <th class="num">Balance (Dr)</th>
                    <th class="num">Balance (Cr)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= View::e($appUrl . '/admin/ledgers/' . $row['ledger_id'] . '/statement') ?>"><?= View::e($row['ledger_name']) ?></a></td>
                        <td class="muted"><?= View::e($row['ledger_code']) ?></td>
                        <td><?= View::e($row['chart_name']) ?></td>
                        <td><?= View::e($row['master_name']) ?></td>
                        <td class="num"><?= number_format((float) $row['debit'], 2) ?></td>
                        <td class="num"><?= number_format((float) $row['credit'], 2) ?></td>
                        <td class="num"><?= $row['balance_debit'] > 0 ? number_format((float) $row['balance_debit'], 2) : '' ?></td>
                        <td class="num"><?= $row['balance_credit'] > 0 ? number_format((float) $row['balance_credit'], 2) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td class="num"><strong><?= number_format((float) $total['debit'], 2) ?></strong></td>
                    <td class="num"><strong><?= number_format((float) $total['credit'], 2) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
