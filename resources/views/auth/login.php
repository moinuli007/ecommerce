<?php

use App\Core\View;

/** @var string $appName */
/** @var string $appUrl */
/** @var string $next */
/** @var array<int,array{0:string,1:string}> $messages */

$messages = $messages ?? [];
$next     = $next ?? '/admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= View::e($appName ?? '') ?></title>
    <style>
        :root {
            --bg:#f3f5f9; --card:#fff; --fg:#1c2024; --muted:#6b7280; --line:#e4e8ee;
            --brand:#ff6b35; --brand-soft:#fff1eb;
            --shadow:0 4px 6px -1px rgba(16,24,40,.07), 0 10px 24px -4px rgba(16,24,40,.08);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:#101317; --card:#181c22; --fg:#e8eaed; --muted:#98a2b3; --line:#262c34;
                --brand:#ff7a45; --brand-soft:#2a1a12; --shadow:0 10px 30px rgba(0,0,0,.5);
            }
        }
        * { box-sizing:border-box; }
        body {
            margin:0; min-height:100vh; display:grid; place-items:center; padding:1.25rem;
            background:var(--bg); color:var(--fg);
            font:15px/1.55 system-ui, "Noto Sans Bengali", "Segoe UI", sans-serif;
        }
        .box {
            background:var(--card); border:1px solid var(--line); border-radius:14px;
            padding:1.85rem 1.6rem; width:min(390px,100%); box-shadow:var(--shadow);
        }
        .brand { display:flex; align-items:center; gap:.6rem; justify-content:center; margin-bottom:.35rem; }
        .logo-mark {
            width:38px; height:38px; border-radius:10px; background:var(--brand); color:#fff;
            display:grid; place-items:center; font-weight:800; font-size:1.1rem;
        }
        .brand strong { font-size:1.12rem; }
        .sub { text-align:center; color:var(--muted); font-size:.87rem; margin:0 0 1.35rem; }
        label { display:block; font-size:.82rem; color:var(--muted); margin:.9rem 0 .3rem; font-weight:500; }
        input {
            width:100%; font:inherit; padding:.62rem .7rem; border:1px solid var(--line);
            border-radius:9px; background:var(--bg); color:var(--fg);
        }
        input:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-soft); }
        button {
            width:100%; margin-top:1.35rem; font:inherit; font-weight:600; padding:.68rem;
            border:0; border-radius:9px; background:var(--brand); color:#fff; cursor:pointer;
        }
        button:hover { filter:brightness(.94); }
        .msg { padding:.6rem .85rem; border-radius:9px; margin-bottom:.5rem; font-size:.88rem; }
        .msg-e { background:#fdecec; color:#8c1c1c; }
        .msg-s { background:#e8f6ec; color:#14532d; }
        .msg-w { background:#fdf3e0; color:#7c4a03; }
        .msg-i { background:#e8f0fd; color:#1e3a8a; }
        @media (prefers-color-scheme: dark) {
            .msg-e { background:#3a1a1a; color:#fca5a5; }
            .msg-s { background:#12301f; color:#86efac; }
            .msg-w { background:#3a2a10; color:#fcd34d; }
            .msg-i { background:#152244; color:#93c5fd; }
        }
        .foot { text-align:center; margin:1.15rem 0 0; font-size:.8rem; color:var(--muted); }
        .foot a { color:var(--brand); text-decoration:none; }
    </style>
</head>
<body>
<form class="box" method="post" action="<?= View::e(($appUrl ?? '') . '/admin/login') ?>">
    <div class="brand">
        <span class="logo-mark"><?= View::e(mb_substr((string) ($appName ?? 'E'), 0, 1)) ?></span>
        <strong><?= View::e($appName ?? '') ?></strong>
    </div>
    <p class="sub">Sign in to the admin panel</p>

    <?php foreach ($messages as $message): ?>
        <div class="msg msg-<?= View::e($message[0]) ?>"><?= View::e($message[1]) ?></div>
    <?php endforeach; ?>

    <input type="hidden" name="next" value="<?= View::e($next) ?>">

    <label for="username">Email or Mobile</label>
    <input id="username" name="username" autocomplete="username" required autofocus>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>

    <button type="submit">Login</button>

    <p class="foot"><a href="<?= View::e($appUrl ?? '/') ?>">← Back to store</a></p>
</form>
</body>
</html>
