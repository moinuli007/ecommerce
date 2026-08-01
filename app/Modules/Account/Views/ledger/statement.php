<?php

use App\Core\View;

/** @var array<string,mixed> $statement */
/** @var array<string,string> $filters */
/** @var string $appUrl */

$ledger  = $statement['ledger'] ?? [];
$rows    = $statement['rows'] ?? [];
$opening = $statement['opening'] ?? ['debit' => 0, 'credit' => 0, 'balance' => 0, 'side' => ''];
$closing = $statement['closing'] ?? ['debit' => 0, 'credit' => 0, 'balance' => 0, 'side' => ''];
$total   = $statement['total'] ?? ['debit' => 0, 'credit' => 0];
?>
<?php if ($ledger === []): ?>
    <div class="card"><div class="empty">লেজার পাওয়া যায়নি।</div></div>
<?php else: ?>
    <div class="card">
        <p style="margin:0 0 .75rem">
            <strong><?= View::e($ledger['name']) ?></strong>
            <span class="muted"><?= View::e($ledger['code']) ?> · <?= View::e($ledger['chart_name']) ?>
                · <?= View::e($ledger['master_name']) ?> (<?= View::e($ledger['nature']) ?>)</span>
        </p>

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
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>তারিখ</th>
                    <th>ভাউচার</th>
                    <th>ধরন</th>
                    <th>বিপরীত হিসাব</th>
                    <th>বিবরণ</th>
                    <th class="num">ডেবিট</th>
                    <th class="num">ক্রেডিট</th>
                    <th class="num">ব্যালেন্স</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td colspan="7"><strong>ওপেনিং ব্যালেন্স</strong></td>
                    <td class="num"><strong><?= number_format((float) $opening['balance'], 2) ?></strong></td>
                </tr>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= View::e($row['date']) ?></td>
                        <td><a href="<?= View::e($appUrl . '/admin/vouchers/' . $row['voucher_id']) ?>"><?= View::e($row['voucher_code']) ?></a></td>
                        <td><?= View::e($row['voucher_type']) ?></td>
                        <td><?= View::e($row['particulars']) ?></td>
                        <td><?= View::e($row['note']) ?></td>
                        <td class="num"><?= $row['debit'] > 0 ? number_format((float) $row['debit'], 2) : '' ?></td>
                        <td class="num"><?= $row['credit'] > 0 ? number_format((float) $row['credit'], 2) : '' ?></td>
                        <td class="num"><?= number_format((float) $row['running_balance'], 2) ?> <span class="muted"><?= View::e($row['running_side']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="5"><strong>মোট</strong></td>
                    <td class="num"><strong><?= number_format((float) $total['debit'], 2) ?></strong></td>
                    <td class="num"><strong><?= number_format((float) $total['credit'], 2) ?></strong></td>
                    <td class="num"><strong><?= number_format((float) $closing['balance'], 2) ?> <?= View::e($closing['side']) ?></strong></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>
