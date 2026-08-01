<?php

use App\Core\View;

/** @var array<string,mixed> $voucher */
?>
<?php if ($voucher === []): ?>
    <div class="card"><div class="empty">ভাউচার পাওয়া যায়নি।</div></div>
<?php else: ?>
    <div class="card">
        <table>
            <tr><th>কোড</th><td><?= View::e($voucher['code']) ?></td>
                <th>তারিখ</th><td><?= View::e($voucher['date_display']) ?></td></tr>
            <tr><th>ধরন</th><td><?= View::e($voucher['type']) ?></td>
                <th>রেফারেন্স</th><td><?= View::e($voucher['reference']) ?></td></tr>
            <tr><th>বিবরণ</th><td colspan="3"><?= View::e($voucher['note']) ?></td></tr>
            <tr><th>এন্ট্রি করেছেন</th><td><?= View::e($voucher['created_by_name']) ?></td>
                <th>পরিমাণ</th><td><?= number_format((float) $voucher['amount'], 2) ?></td></tr>
        </table>
    </div>

    <div class="card">
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>লেজার</th>
                    <th>কোড</th>
                    <th>বিবরণ</th>
                    <th class="num">ডেবিট</th>
                    <th class="num">ক্রেডিট</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($voucher['lines'] as $line): ?>
                    <tr>
                        <td><?= View::e($line['ledger_name']) ?></td>
                        <td class="muted"><?= View::e($line['ledger_code']) ?></td>
                        <td><?= View::e($line['note']) ?></td>
                        <td class="num"><?= $line['debit'] > 0 ? number_format((float) $line['debit'], 2) : '' ?></td>
                        <td class="num"><?= $line['credit'] > 0 ? number_format((float) $line['credit'], 2) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">কথায়: <?= View::e($voucher['amount_in_words']) ?></p>
    </div>
<?php endif; ?>
