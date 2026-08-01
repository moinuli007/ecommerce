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
            <label for="from">থেকে</label>
            <input id="from" name="from" type="date" value="<?= View::e($filters['from']) ?>">
        </div>
        <div>
            <label for="to">পর্যন্ত</label>
            <input id="to" name="to" type="date" value="<?= View::e($filters['to']) ?>">
        </div>
        <button type="submit">দেখুন</button>
    </form>
</div>

<div class="card">
    <?php if (!($report['is_balanced'] ?? true)): ?>
        <div class="msg msg-e">
            ⚠ ট্রায়াল ব্যালেন্স মিলছে না — ডেবিট আর ক্রেডিটের যোগফল সমান নয়।
            কোথাও সরাসরি DB-তে এন্ট্রি করা হয়েছে কি না দেখুন।
        </div>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <div class="empty">এই সময়ে কোনো লেনদেন নাই।</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>লেজার</th>
                    <th>কোড</th>
                    <th>চার্ট</th>
                    <th>মাস্টার</th>
                    <th class="num">ডেবিট</th>
                    <th class="num">ক্রেডিট</th>
                    <th class="num">ব্যালেন্স (Dr)</th>
                    <th class="num">ব্যালেন্স (Cr)</th>
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
                    <td colspan="4"><strong>মোট</strong></td>
                    <td class="num"><strong><?= number_format((float) $total['debit'], 2) ?></strong></td>
                    <td class="num"><strong><?= number_format((float) $total['credit'], 2) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
