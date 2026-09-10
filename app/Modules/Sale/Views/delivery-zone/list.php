<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $zones — ডেলিভারি জোন লিস্ট */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <div class="filters">
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/delivery-zones/create') ?>">+ New Zone</a>
    </div>
</div>

<div class="card">
    <?php if ($zones === []): ?>
        <div class="empty">
            No delivery zones found.
            <a href="<?= View::e($appUrl . '/admin/delivery-zones/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th class="num">Delivery Fee</th>
                    <th class="num">Free Delivery Over</th>
                    <th>Default</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($zones as $zone): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/delivery-zones/' . $zone['id'] . '/edit') ?>">
                                <?= View::e($zone['name']) ?>
                            </a>
                        </td>
                        <td class="num"><?= number_format((float) $zone['fee'], 2) ?></td>
                        <td class="num">
                            <?= (float) $zone['free_delivery_threshold'] > 0
                                ? number_format((float) $zone['free_delivery_threshold'], 2)
                                : '—' ?>
                        </td>
                        <td><?= (int) $zone['is_default'] === 1 ? '<span class="pill">Default</span>' : '—' ?></td>
                        <td>
                            <?= (int) $zone['isActive'] === 1
                                ? 'Active'
                                : '<span class="pill off">Inactive</span>' ?>
                        </td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $zone['id'] ?>">Delete</button></td>
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
            if (!confirm('Delete this delivery zone?')) { return; }

            if (await api('/delivery-zones/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
        });
    });
})();
</script>
