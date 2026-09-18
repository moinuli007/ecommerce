<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $users — অ্যাডমিন ইউজার লিস্ট */
/** @var array<string,string> $filters — q */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    .pill.role { background:rgba(37,99,235,.12); color:var(--info); }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/users') ?>">
        <div>
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Name, email or phone">
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/users/create') ?>">+ New Admin User</a>
    </form>
</div>

<div class="card">
    <?php if ($users === []): ?>
        <div class="empty">
            No admin users found.
            <a href="<?= View::e($appUrl . '/admin/users/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/users/' . $user['id'] . '/edit') ?>">
                                <?= View::e($user['name']) ?>
                            </a>
                        </td>
                        <td><span class="pill role"><?= View::e($user['type_label']) ?></span></td>
                        <td><?= View::e($user['email'] ?: '—') ?></td>
                        <td><?= View::e($user['phone'] ?: '—') ?></td>
                        <td class="muted"><?= (int) $user['last_login'] > 0 ? date('d M Y, h:i A', (int) $user['last_login']) : 'Never' ?></td>
                        <td>
                            <?= (int) $user['isActive'] === 1
                                ? 'Active'
                                : '<span class="pill off">Inactive</span>' ?>
                        </td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $user['id'] ?>">Deactivate</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // ডিঅ্যাক্টিভেট — কনফার্ম করে API কল, তারপর পেজ রিলোড
    document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Deactivate this admin user? They will no longer be able to log in.')) { return; }

            if (await api('/users/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
        });
    });
})();
</script>
