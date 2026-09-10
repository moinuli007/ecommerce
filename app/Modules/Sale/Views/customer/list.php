<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $customers — কাস্টমার লিস্ট */
/** @var array<string,string> $filters — q */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/customers') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Name, phone or email">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/customers/create') ?>">+ New Customer</a>
    </form>
</div>

<div class="card">
    <?php if ($customers === []): ?>
        <div class="empty">
            No customers found.
            <a href="<?= View::e($appUrl . '/admin/customers/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="num">Opening Balance</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/customers/' . $customer['id'] . '/edit') ?>">
                                <?= View::e($customer['name']) ?>
                            </a>
                            <?= (int) $customer['user_id'] === 0 ? '<span class="pill">Guest</span>' : '' ?>
                        </td>
                        <td><?= View::e($customer['phone']) ?></td>
                        <td><?= View::e($customer['email'] ?: '—') ?></td>
                        <td class="num"><?= number_format((float) $customer['opening_balance'], 2) ?></td>
                        <td>
                            <?= (int) $customer['isActive'] === 1
                                ? 'Active'
                                : '<span class="pill off">Inactive</span>' ?>
                        </td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $customer['id'] ?>">Delete</button></td>
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
            if (!confirm('Delete this customer?')) { return; }

            if (await api('/customers/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
        });
    });
})();
</script>
