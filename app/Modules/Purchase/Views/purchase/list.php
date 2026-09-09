<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $purchases */
/** @var array<string,string> $filters */
/** @var string $appUrl */
?>
<style>
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/purchases') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Purchase code or bill no">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/purchases/create') ?>">+ New Purchase</a>
    </form>
</div>

<div class="card">
    <?php if ($purchases === []): ?>
        <div class="empty">
            No purchases yet.
            <a href="<?= View::e($appUrl . '/admin/purchases/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Bill No</th>
                    <th class="num">Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($purchases as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/purchases/' . $row['id']) ?>">
                                <?= View::e($row['code']) ?>
                            </a>
                        </td>
                        <td><?= (int) $row['invoice_date'] > 0 ? date('Y-m-d', (int) $row['invoice_date']) : '—' ?></td>
                        <td><?= View::e($row['supplier_name'] ?? '') ?></td>
                        <td><?= View::e($row['supplier_invoice_no'] ?: '—') ?></td>
                        <td class="num"><?= number_format((float) $row['total'], 2) ?></td>
                        <td>
                            <a class="btn ghost sm" href="<?= View::e($appUrl . '/admin/purchases/' . $row['id'] . '/edit') ?>">Edit</a>
                            <button type="button" class="ghost sm" data-del="<?= (int) $row['id'] ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this purchase? Stock and vouchers will be reversed.')) { return; }

            if (await api('/purchases/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
        });
    });
})();
</script>
