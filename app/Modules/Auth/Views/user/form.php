<?php

use App\Core\View;

/** @var array<string,mixed> $user — এডিটে ভরা, নতুনে খালি */
/** @var string $appUrl */

$isEdit = ($user['id'] ?? 0) > 0;
$v = static fn (string $key, mixed $default = '') => $user[$key] ?? $default;
?>
<div class="card" style="max-width:560px">
    <form id="user-form">
        <input type="hidden" id="id" value="<?= (int) ($user['id'] ?? 0) ?>">

        <div style="margin-bottom:.85rem">
            <label for="name">Name *</label>
            <input id="name" name="name" required maxlength="150" style="width:100%"
                   value="<?= View::e($v('name')) ?>">
        </div>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.85rem">
            <div style="flex:1; min-width:180px">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" style="width:100%"
                       value="<?= View::e($v('email')) ?>">
            </div>
            <div style="flex:1; min-width:180px">
                <label for="phone">Phone</label>
                <input id="phone" name="phone" maxlength="30" style="width:100%"
                       value="<?= View::e($v('phone')) ?>">
            </div>
        </div>
        <p class="muted" style="font-size:.8rem;margin:-.5rem 0 .85rem">At least one of email or phone is required — it's what they'll log in with.</p>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.85rem">
            <div style="flex:1; min-width:180px">
                <label for="type">Role</label>
                <select id="type" name="type" style="width:100%">
                    <option value="1" <?= (int) $v('type', 2) === 1 ? 'selected' : '' ?>>Super Admin</option>
                    <option value="2" <?= (int) $v('type', 2) === 2 ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div style="flex:1; min-width:180px">
                <label for="isActive">Status</label>
                <select id="isActive" name="isActive" style="width:100%">
                    <option value="1" <?= (int) $v('isActive', 1) === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int) $v('isActive', 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <?php if (!$isEdit): ?>
            <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem">
                <div style="flex:1; min-width:180px">
                    <label for="password">Password *</label>
                    <input id="password" name="password" type="password" required minlength="6" style="width:100%">
                </div>
                <div style="flex:1; min-width:180px">
                    <label for="password_confirmation">Confirm Password *</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" style="width:100%">
                </div>
            </div>
        <?php endif; ?>

        <button type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/users') ?>">Cancel</a>
    </form>
</div>

<?php if ($isEdit): ?>
<div class="card" style="max-width:560px">
    <div class="card-title">Reset Password</div>
    <p class="muted" style="font-size:.85rem;margin-top:0">
        Sets a new password directly — no need to know the current one.
    </p>
    <form id="reset-password-form">
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
        <button type="submit" class="ghost">Reset Password</button>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    var form = document.getElementById('user-form');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var id = parseInt(document.getElementById('id').value, 10) || 0;

        var body = {
            name:     document.getElementById('name').value,
            email:    document.getElementById('email').value,
            phone:    document.getElementById('phone').value,
            type:     parseInt(document.getElementById('type').value, 10),
            isActive: parseInt(document.getElementById('isActive').value, 10)
        };

        if (!id) {
            body.password              = document.getElementById('password').value;
            body.password_confirmation = document.getElementById('password_confirmation').value;
        }

        var result = id > 0
            ? await api('/users/' + id, body, 'PUT')
            : await api('/users', body, 'POST');

        if (result) {
            location.href = '<?= View::e($appUrl) ?>/admin/users';
        }
    });

    var resetForm = document.getElementById('reset-password-form');

    if (resetForm) {
        resetForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var body = {
                password:              document.getElementById('new_password').value,
                password_confirmation: document.getElementById('new_password_confirmation').value
            };

            var id = parseInt(document.getElementById('id').value, 10) || 0;

            if (await api('/users/' + id + '/password', body, 'PUT')) {
                resetForm.reset();
            }
        });
    }
})();
</script>
