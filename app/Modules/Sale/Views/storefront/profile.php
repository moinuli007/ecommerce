<?php

use App\Core\View;

/** @var array<string,mixed> $user */
/** @var string $appUrl */
?>
<style>
    .profile-wrap { max-width:520px; margin:0 auto; padding:2.5rem 1rem 4rem; }
    .profile-wrap h1 { font-size:1.4rem; margin:0 0 1.5rem; font-weight:800; }
    .profile-card { border:1px solid var(--line); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
    .profile-card h3 { margin:0 0 1rem; font-size:1rem; }
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:.3rem; }
    .field input {
        width:100%; padding:.65rem .75rem; border:1px solid var(--line); border-radius:6px; font-size:.9rem;
    }
    .danger-card { border-color:#e5484d55; }
    .danger-card p { font-size:.85rem; color:var(--muted); margin:0 0 1rem; }
    .btn.danger { background:#e5484d; color:#fff; }
</style>

<div class="wrap profile-wrap">
    <h1>My Account</h1>

    <div class="profile-card">
        <h3>Profile</h3>
        <form id="profile-form">
            <div class="field">
                <label for="name">Full Name *</label>
                <input id="name" name="name" required maxlength="150" value="<?= View::e($user['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="phone">Phone Number *</label>
                <input id="phone" name="phone" required maxlength="20" value="<?= View::e($user['phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" value="<?= View::e($user['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn accent" id="profile-btn">Save Changes</button>
        </form>
    </div>

    <div class="profile-card">
        <h3>Change Password</h3>
        <form id="password-form">
            <div class="field">
                <label for="current_password">Current Password *</label>
                <input id="current_password" name="current_password" type="password" required>
            </div>
            <div class="field">
                <label for="new_password">New Password *</label>
                <input id="new_password" name="new_password" type="password" required minlength="6">
            </div>
            <div class="field">
                <label for="new_password_confirmation">Confirm New Password *</label>
                <input id="new_password_confirmation" name="new_password_confirmation" type="password" required minlength="6">
            </div>
            <button type="submit" class="btn accent" id="password-btn">Change Password</button>
        </form>
    </div>

    <div class="profile-card danger-card">
        <h3>Delete Account</h3>
        <p>
            This deactivates your account and logs you out. Your past orders stay on
            record for our accounts, but you won't be able to log in with this phone
            number again unless you register once more.
        </p>
        <button type="button" class="btn danger" id="delete-btn">Delete My Account</button>
    </div>
</div>

<script>
(function () {
    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var btn = document.getElementById('profile-btn');
            btn.disabled = true;

            var result = await window.accountApi('/profile', {
                name:  document.getElementById('name').value,
                phone: document.getElementById('phone').value,
                email: document.getElementById('email').value
            }, 'PUT');

            if (result) {
                location.reload(); // নেভের "Hi, <name>" আপডেট হওয়ার জন্য
                return;
            }

            btn.disabled = false;
        });
    }

    var passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var btn = document.getElementById('password-btn');
            btn.disabled = true;

            var result = await window.accountApi('/password', {
                current_password:      document.getElementById('current_password').value,
                password:              document.getElementById('new_password').value,
                password_confirmation: document.getElementById('new_password_confirmation').value
            }, 'PUT');

            btn.disabled = false;

            if (result) {
                passwordForm.reset();
            }
        });
    }

    var deleteBtn = document.getElementById('delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function () {
            if (!confirm('Are you sure you want to delete your account? This cannot be undone.')) {
                return;
            }

            deleteBtn.disabled = true;

            var result = await window.accountApi('/account', {}, 'DELETE');

            if (result) {
                location.href = '<?= View::e($appUrl) ?>/';
                return;
            }

            deleteBtn.disabled = false;
        });
    }
})();
</script>
