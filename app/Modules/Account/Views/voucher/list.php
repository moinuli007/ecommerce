<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $vouchers */
/** @var array<string,mixed> $pagination */
/** @var array<int,array<string,mixed>> $types */
/** @var array<string,string> $filters */
/** @var string $appUrl */
?>
<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/vouchers') ?>">
        <div>
            <label for="type">ধরন</label>
            <select id="type" name="type">
                <option value="">সব</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (string) $filters['type'] === (string) $type['id'] ? 'selected' : '' ?>>
                        <?= View::e($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="from">থেকে</label>
            <input id="from" name="from" type="date" value="<?= View::e($filters['from']) ?>">
        </div>
        <div>
            <label for="to">পর্যন্ত</label>
            <input id="to" name="to" type="date" value="<?= View::e($filters['to']) ?>">
        </div>
        <div>
            <label for="code">ভাউচার কোড</label>
            <input id="code" name="code" value="<?= View::e($filters['code']) ?>" placeholder="যেমন JV-000012">
        </div>
        <button type="submit">খুঁজুন</button>
        <a class="ghost" href="<?= View::e($appUrl . '/admin/vouchers/entry') ?>"><button type="button" class="ghost">নতুন ভাউচার</button></a>
    </form>
</div>

<div class="card">
    <?php if ($vouchers === []): ?>
        <div class="empty">কোনো ভাউচার পাওয়া যায়নি।</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>কোড</th>
                    <th>তারিখ</th>
                    <th>ধরন</th>
                    <th>ডেবিট</th>
                    <th>ক্রেডিট</th>
                    <th class="num">পরিমাণ</th>
                    <th>বিবরণ</th>
                    <th>এন্ট্রি</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($vouchers as $voucher): ?>
                    <tr>
                        <td><a href="<?= View::e($appUrl . '/admin/vouchers/' . $voucher['id']) ?>"><?= View::e($voucher['code']) ?></a></td>
                        <td><?= View::e($voucher['date_display']) ?></td>
                        <td><?= View::e($voucher['type']) ?></td>
                        <td><?= View::e($voucher['debit_ledger_name']) ?><?= $voucher['is_compound'] ? ' <span class="muted">+</span>' : '' ?></td>
                        <td><?= View::e($voucher['credit_ledger_name']) ?><?= $voucher['is_compound'] ? ' <span class="muted">+</span>' : '' ?></td>
                        <td class="num"><?= number_format((float) $voucher['amount'], 2) ?></td>
                        <td><?= View::e($voucher['note']) ?></td>
                        <td class="muted"><?= View::e($voucher['created_by_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <p class="muted">
                মোট <?= (int) $pagination['total'] ?> টি —
                পৃষ্ঠা <?= (int) $pagination['page'] ?> / <?= (int) $pagination['pages'] ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
