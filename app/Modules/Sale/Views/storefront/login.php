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
    <h1>Login</h1>
    <p class="sub">Login with your phone number or email.</p>

    <form id="login-form">
        <div class="field">
            <label for="username">Phone or Email *</label>
            <input id="username" name="username" required>
        </div>
        <div class="field">
            <label for="password">Password *</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button type="submit" class="btn accent block" id="login-btn">Login</button>
    </form>

    <p class="auth-alt">New here? <a href="<?= View::e($appUrl) ?>/register">Create an account</a></p>
</div>

<script>
(function () {
    var form = document.getElementById('login-form');
    if (!form) { return; }

    // ?next= শুধু নিজের সাইটের ভেতরের পাথ হলেই মানা হবে — //evil.com বা
    // https://evil.com দিয়ে বাইরে পাঠানো (open redirect) ঠেকাতে, ঠিক
    // LoginController::safeNext() এর একই নিয়ম (doc/11-customer-account.md C-11)
    function safeNext() {
        var params = new URLSearchParams(location.search);
        var next   = params.get('next') || '';

        if (next === '' || next.charAt(0) !== '/' || next.charAt(1) === '/') {
            return '<?= View::e($appUrl) ?>/profile';
        }

        return '<?= View::e($appUrl) ?>' + next;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var btn = document.getElementById('login-btn');
        btn.disabled = true;
        btn.textContent = 'Logging in…';

        var result = await window.accountApi('/login', {
            username: document.getElementById('username').value,
            password: document.getElementById('password').value
        }, 'POST');

        if (result && result.user && result.user.is_admin) {
            await window.accountApi('/logout', {}, 'POST');
            window.storeToast('e', 'Please use the admin panel to log in.');
            btn.disabled = false;
            btn.textContent = 'Login';
            return;
        }

        btn.disabled = false;
        btn.textContent = 'Login';

        if (result) {
            location.href = safeNext();
        }
    });
})();
</script>
