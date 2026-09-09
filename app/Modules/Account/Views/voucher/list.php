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
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (string) $filters['type'] === (string) $type['id'] ? 'selected' : '' ?>>
                        <?= View::e($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= View::e($filters['from']) ?>">
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= View::e($filters['to']) ?>">
        </div>
        <div>
            <label for="code">Voucher Code</label>
            <input id="code" name="code" value="<?= View::e($filters['code']) ?>" placeholder="e.g. JV-000012">
        </div>
        <button type="submit">Search</button>
        <a class="ghost" href="<?= View::e($appUrl . '/admin/vouchers/entry') ?>"><button type="button" class="ghost">New Voucher</button></a>
    </form>
</div>

<div class="card">
    <?php if ($vouchers === []): ?>
        <div class="empty">No vouchers found.</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th class="num">Amount</th>
                    <th>Note</th>
                    <th>Entered By</th>
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
                Total <?= (int) $pagination['total'] ?> —
                page <?= (int) $pagination['page'] ?> / <?= (int) $pagination['pages'] ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
