<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $returns */
/** @var array<string,string> $filters */
/** @var string $appUrl */
?>
<style>button.sm{padding:.25rem .55rem;font-size:.78rem}</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/stock/returns') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Return code">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/stock/returns/create') ?>">+ New Return</a>
    </form>
</div>

<div class="card">
    <?php if ($returns === []): ?>
        <div class="empty">No purchase returns yet.</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead><tr><th>Code</th><th>Date</th><th>Supplier</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($returns as $row): ?>
                    <tr>
                        <td><?= View::e($row['code']) ?></td>
                        <td><?= (int) $row['return_date'] > 0 ? date('Y-m-d', (int) $row['return_date']) : '—' ?></td>
                        <td><?= View::e($row['supplier_name'] ?? '') ?></td>
                        <td class="num"><?= number_format((float) $row['sub_total'], 2) ?></td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $row['id'] ?>">Delete</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-del]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        if (!confirm('Delete this return? Stock and vouchers will be reversed.')) { return; }
        if (await api('/stock/returns/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
    });
});
</script>
