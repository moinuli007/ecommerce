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
            <span class="muted"><?= View::e($user['type_label'] ?? '') ?></span>
        </div>
    </div>

    <table>
        <tr><th style="width:35%">Email</th><td><?= View::e($user['email'] ?? '—') ?></td></tr>
        <tr><th>Phone</th><td><?= View::e($user['phone'] ?? '—') ?></td></tr>
        <tr><th>User ID</th><td><?= (int) ($user['id'] ?? 0) ?></td></tr>
        <tr><th>Type</th><td><?= View::e($user['type_label'] ?? '') ?></td></tr>
    </table>

    <div style="margin-top:1rem">
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/logout') ?>">Logout</a>
    </div>
</div>

<div class="card" style="max-width:560px">
    <div class="card-title">Change Password</div>
    <form id="own-password-form">
        <div style="margin-bottom:.85rem">
            <label for="current_password">Current Password *</label>
            <input id="current_password" name="current_password" type="password" required style="width:100%">
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem">
            <div style="flex:1; min-width:180px">
                <label for="new_password">New Password *</label>
                <input id="new_password" name="password" type="password" required minlength="6" style="width:100%">
            </div>
            <div style="flex:1; min-width:180px">
                <label for="new_password_confirmation">Confirm New Password *</label>
                <input id="new_password_confirmation" name="password_confirmation" type="password" required minlength="6" style="width:100%">
            </div>
        </div>
        <button type="submit">Change Password</button>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('own-password-form');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var body = {
            current_password:      document.getElementById('current_password').value,
            password:              document.getElementById('new_password').value,
            password_confirmation: document.getElementById('new_password_confirmation').value
        };

        if (await api('/users/me/password', body, 'PUT')) {
            form.reset();
        }
    });
})();
</script>
