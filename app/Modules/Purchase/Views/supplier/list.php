<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $suppliers — সাপ্লায়ার লিস্ট */
/** @var array<string,string> $filters — q */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/suppliers') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Name, phone or code">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/suppliers/create') ?>">+ New Supplier</a>
    </form>
</div>

<div class="card">
    <?php if ($suppliers === []): ?>
        <div class="empty">
            No suppliers found.
            <a href="<?= View::e($appUrl . '/admin/suppliers/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th class="num">Opening Balance</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td><code class="muted"><?= View::e($supplier['code']) ?></code></td>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/suppliers/' . $supplier['id'] . '/edit') ?>">
                                <?= View::e($supplier['name']) ?>
                            </a>
                        </td>
                        <td><?= View::e($supplier['phone'] ?: '—') ?></td>
                        <td class="num"><?= number_format((float) $supplier['opening_balance'], 2) ?></td>
                        <td>
                            <?= (int) $supplier['isActive'] === 1
                                ? 'Active'
                                : '<span class="pill off">Inactive</span>' ?>
                        </td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $supplier['id'] ?>">Delete</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // ডিলিট — কনফার্ম করে API কল, তারপর পেজ রিলোড
    document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this supplier?')) { return; }

            if (await api('/suppliers/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
        });
    });
})();
</script>
