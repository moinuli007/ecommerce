<?php

use App\Core\View;

/** @var array<string,mixed> $user */
/** @var string $appUrl */
?>
<div class="card" style="max-width:560px">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
        <span class="user-letter" style="width:56px;height:56px;font-size:1.4rem">
            <?= View::e(mb_substr((string) ($user['name'] ?? '?'), 0, 1)) ?>
        </span>
        <div>
            <strong style="font-size:1.1rem;display:block"><?= View::e($user['name'] ?? '') ?></strong>
            <span class="muted"><?= $user['is_admin'] ?? false ? 'Admin' : 'Customer' ?></span>
        </div>
    </div>

    <table>
        <tr><th style="width:35%">Email</th><td><?= View::e($user['email'] ?? '—') ?></td></tr>
        <tr><th>Phone</th><td><?= View::e($user['phone'] ?? '—') ?></td></tr>
        <tr><th>User ID</th><td><?= (int) ($user['id'] ?? 0) ?></td></tr>
        <tr><th>Type</th><td><?= (int) ($user['type'] ?? 0) === 1 ? 'Super Admin' : 'Staff' ?></td></tr>
    </table>

    <p class="muted" style="margin:1rem 0 0;font-size:.86rem">
        Profile editing and password change are coming in Phase 7.
    </p>

    <div style="margin-top:1rem">
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/logout') ?>">Logout</a>
    </div>
</div>
