<?php

use App\Core\View;

/** @var string $appUrl */
?>
<style>
    .auth-wrap { max-width:420px; margin:0 auto; padding:2.5rem 1rem 4rem; }
    .auth-wrap h1 { font-size:1.4rem; margin:0 0 .4rem; font-weight:800; }
    .auth-wrap .sub { color:var(--muted); font-size:.85rem; margin:0 0 1.5rem; }
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:.3rem; }
    .field input {
        width:100%; padding:.65rem .75rem; border:1px solid var(--line); border-radius:6px; font-size:.9rem;
    }
    .auth-alt { margin-top:1.25rem; font-size:.85rem; text-align:center; color:var(--muted); }
    .auth-alt a { color:var(--brand); font-weight:600; }
</style>

<div class="wrap auth-wrap">
    <h1>Create Account</h1>
    <p class="sub">Save your details for faster checkout next time.</p>

    <form id="register-form">
        <div class="field">
            <label for="name">Full Name *</label>
            <input id="name" name="name" required maxlength="150">
        </div>
        <div class="field">
            <label for="phone">Phone Number *</label>
            <input id="phone" name="phone" required maxlength="20" placeholder="01XXXXXXXXX">
        </div>
        <div class="field">
            <label for="email">Email (optional)</label>
            <input id="email" name="email" type="email" maxlength="150">
        </div>
        <div class="field">
            <label for="password">Password *</label>
            <input id="password" name="password" type="password" required minlength="6">
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm Password *</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6">
        </div>
        <button type="submit" class="btn accent block" id="register-btn">Create Account</button>
    </form>

    <p class="auth-alt">Already have an account? <a href="<?= View::e($appUrl) ?>/login">Login</a></p>
</div>

<script>
(function () {
    var form = document.getElementById('register-form');
    if (!form) { return; }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var btn = document.getElementById('register-btn');
        btn.disabled = true;
        btn.textContent = 'Creating account…';

        var result = await window.accountApi('/register', {
            name:                  document.getElementById('name').value,
            phone:                 document.getElementById('phone').value,
            email:                 document.getElementById('email').value,
            password:              document.getElementById('password').value,
            password_confirmation: document.getElementById('password_confirmation').value
        }, 'POST');

        btn.disabled = false;
        btn.textContent = 'Create Account';

        if (result) {
            location.href = '<?= View::e($appUrl) ?>/profile';
        }
    });
})();
</script>
