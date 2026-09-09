<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $adjustments */
/** @var array<string,string> $filters */
/** @var string $appUrl */
?>
<style>button.sm{padding:.25rem .55rem;font-size:.78rem}</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/stock/adjustments') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Adjustment code">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/stock/adjustments/create') ?>">+ New Adjustment</a>
    </form>
</div>

<div class="card">
    <?php if ($adjustments === []): ?>
        <div class="empty">
            No adjustments yet.
            <a href="<?= View::e($appUrl . '/admin/stock/adjustments/create') ?>" style="color:var(--brand)">Add one</a>
            (use this for opening stock).
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead><tr><th>Code</th><th>Date</th><th>Reason</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($adjustments as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/stock/adjustments/' . $row['id'] . '/edit') ?>">
                                <?= View::e($row['code']) ?>
                            </a>
                        </td>
                        <td><?= (int) $row['adjust_date'] > 0 ? date('Y-m-d', (int) $row['adjust_date']) : '—' ?></td>
                        <td><?= View::e($row['reason_label'] ?? '') ?></td>
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
        if (!confirm('Delete this adjustment? Stock and vouchers will be reversed.')) { return; }
        if (await api('/stock/adjustments/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
    });
});
</script>
