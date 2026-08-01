<?php

use App\Core\Auth;
use App\Core\Menu;
use App\Core\Request;
use App\Core\View;

/**
 * অ্যাডমিন লেআউট — erp_saas এর কাঠামো অনুসরণ করে:
 *
 *   ┌──────────────────────────────────────────────────────┐
 *   │ লোগো ⟨⟩            [ভাষা] [ফুলস্ক্রিন] [প্রোফাইল ▾] │  ← header
 *   ├────────────┬─────────────────────────────────────────┤
 *   │  সাইডবার   │  page-header (টাইটেল + ব্রেডক্রাম্ব)     │
 *   │  মেনু      │  content                                 │
 *   └────────────┴─────────────────────────────────────────┘
 *
 * erp_saas এর মতো: টপ বারের সব কিছু ডান পাশে, প্রোফাইল ড্রপডাউনে
 * My Profile / Settings / Logout। সাইডবার কলাপসিবল (⟨⟩ বাটন), মোবাইলে ড্রয়ার।
 *
 * কোনো এক্সটার্নাল CSS/JS লোড হয় না — আইকন ইনলাইন SVG (App\Core\Menu::icon)।
 */

/** @var string $content */
/** @var string $title */
/** @var string $appName */
/** @var string $appUrl */
/** @var array<int,array{0:string,1:string}> $messages */

$messages = $messages ?? [];
$current  = Request::path();
$user     = Auth::user();
$crumb    = Menu::locate($current);
$initial  = mb_substr((string) ($user['name'] ?? '?'), 0, 1);
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? 'Admin') ?> — <?= View::e($appName ?? '') ?></title>
    <style>
        :root {
            --bg:#f3f5f9; --card:#fff; --fg:#1c2024; --muted:#6b7280; --line:#e4e8ee;
            --brand:#ff6b35; --brand-soft:#fff1eb;
            --sidebar-bg:#fff; --sidebar-fg:#4b5563; --header-bg:#fff;
            --success:#16a34a; --danger:#dc2626; --warning:#d97706; --info:#2563eb;
            --shadow:0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.1);
            --sidebar-w:248px; --sidebar-mini:68px; --header-h:60px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:#101317; --card:#181c22; --fg:#e8eaed; --muted:#98a2b3; --line:#262c34;
                --brand:#ff7a45; --brand-soft:#2a1a12;
                --sidebar-bg:#181c22; --sidebar-fg:#b6bfcc; --header-bg:#181c22;
                --shadow:0 1px 2px rgba(0,0,0,.4);
            }
        }
        :root[data-theme="light"] {
            --bg:#f3f5f9; --card:#fff; --fg:#1c2024; --muted:#6b7280; --line:#e4e8ee;
            --brand-soft:#fff1eb; --sidebar-bg:#fff; --sidebar-fg:#4b5563; --header-bg:#fff;
            --shadow:0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.1);
        }
        :root[data-theme="dark"] {
            --bg:#101317; --card:#181c22; --fg:#e8eaed; --muted:#98a2b3; --line:#262c34;
            --brand:#ff7a45; --brand-soft:#2a1a12;
            --sidebar-bg:#181c22; --sidebar-fg:#b6bfcc; --header-bg:#181c22;
            --shadow:0 1px 2px rgba(0,0,0,.4);
        }

        * { box-sizing:border-box; }
        html, body { height:100%; }
        body {
            margin:0; background:var(--bg); color:var(--fg);
            font:14px/1.55 system-ui, "Noto Sans Bengali", "Segoe UI", sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        a { color:inherit; text-decoration:none; }
        svg { width:18px; height:18px; flex:0 0 auto; }

        /* ------------------------------------------------------------ হেডার */
        .header {
            position:fixed; inset:0 0 auto 0; height:var(--header-h); z-index:30;
            background:var(--header-bg); border-bottom:1px solid var(--line);
            display:flex; align-items:center; padding-right:.75rem;
        }
        .header-left {
            width:var(--sidebar-w); height:100%; flex:0 0 auto;
            display:flex; align-items:center; gap:.5rem; padding:0 .9rem;
            border-right:1px solid var(--line); transition:width .18s ease;
        }
        .logo { display:flex; align-items:center; gap:.55rem; font-weight:700; font-size:1.02rem; min-width:0; }
        .logo-mark {
            width:30px; height:30px; flex:0 0 auto; border-radius:8px; background:var(--brand);
            color:#fff; display:grid; place-items:center; font-weight:800; font-size:.9rem;
        }
        .logo-text { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        #toggle-btn {
            margin-left:auto; display:grid; place-items:center; width:30px; height:30px;
            border-radius:7px; color:var(--muted); cursor:pointer; border:0; background:transparent;
        }
        #toggle-btn:hover { background:var(--bg); color:var(--fg); }
        #toggle-btn svg { transition:transform .18s ease; }

        .user-menu { list-style:none; display:flex; align-items:center; gap:.35rem; margin:0 0 0 auto; padding:0; }
        .nav-box {
            width:36px; height:36px; border-radius:9px; border:1px solid var(--line);
            display:grid; place-items:center; color:var(--muted); background:transparent; cursor:pointer;
        }
        .nav-box:hover { color:var(--brand); border-color:var(--brand); }

        /* প্রোফাইল ড্রপডাউন */
        .userset { display:flex; align-items:center; gap:.55rem; padding:.25rem .4rem;
                   border-radius:9px; cursor:pointer; background:transparent; border:0; color:inherit; font:inherit; }
        .userset:hover { background:var(--bg); }
        .user-letter {
            width:34px; height:34px; border-radius:50%; background:var(--brand); color:#fff;
            display:grid; place-items:center; font-weight:700; flex:0 0 auto;
        }
        .user-detail { text-align:left; line-height:1.2; }
        .user-name { display:block; font-weight:600; font-size:.88rem; }
        .user-role { display:block; font-size:.74rem; color:var(--muted); }
        .main-drop { position:relative; }
        .menu-drop-user {
            position:absolute; right:0; top:calc(100% + .5rem); width:236px; z-index:40;
            background:var(--card); border:1px solid var(--line); border-radius:12px;
            box-shadow:var(--shadow); padding:.4rem; display:none;
        }
        .main-drop.open .menu-drop-user { display:block; }
        .profileset { display:flex; align-items:center; gap:.6rem; padding:.6rem .55rem .7rem; }
        .profileset .user-letter { width:40px; height:40px; font-size:1rem; }
        .dropdown-item {
            display:flex; align-items:center; gap:.6rem; padding:.55rem; border-radius:8px;
            color:var(--fg); font-size:.88rem;
        }
        .dropdown-item:hover { background:var(--bg); }
        .dropdown-item svg { width:16px; height:16px; color:var(--muted); }
        .dropdown-item.danger, .dropdown-item.danger svg { color:var(--danger); }
        .drop-line { height:1px; background:var(--line); margin:.35rem .2rem; }

        /* ----------------------------------------------------------- সাইডবার */
        .sidebar {
            position:fixed; top:var(--header-h); bottom:0; left:0; width:var(--sidebar-w); z-index:20;
            background:var(--sidebar-bg); border-right:1px solid var(--line);
            overflow-y:auto; overscroll-behavior:contain; padding:.85rem .6rem 2rem;
            transition:width .18s ease, transform .18s ease;
        }
        .menu-group + .menu-group { margin-top:1.1rem; }
        .menu-title {
            font-size:.7rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase;
            color:var(--muted); padding:0 .65rem .45rem; white-space:nowrap;
        }
        .menu-link {
            display:flex; align-items:center; gap:.7rem; padding:.6rem .65rem; border-radius:9px;
            color:var(--sidebar-fg); font-size:.9rem; margin-bottom:.15rem; white-space:nowrap;
        }
        .menu-link svg { color:var(--muted); }
        .menu-link:hover { background:var(--bg); color:var(--fg); }
        .menu-link.active { background:var(--brand); color:#fff; font-weight:600; }
        .menu-link.active svg { color:#fff; }
        .menu-label { overflow:hidden; text-overflow:ellipsis; }

        /* ------------------------------------------------- মূল কনটেন্ট এলাকা */
        .page-wrapper {
            margin-left:var(--sidebar-w); padding-top:var(--header-h); min-height:100%;
            transition:margin-left .18s ease;
        }
        .content { padding:1.25rem; max-width:1400px; }
        .page-header {
            display:flex; align-items:flex-end; gap:1rem; flex-wrap:wrap; margin-bottom:1.1rem;
        }
        .page-header h1 { margin:0; font-size:1.28rem; font-weight:700; }
        .breadcrumb { font-size:.82rem; color:var(--muted); margin-top:.15rem; }
        .page-actions { margin-left:auto; display:flex; gap:.5rem; flex-wrap:wrap; }

        /* কলাপসড অবস্থা (ডেস্কটপ) */
        body.mini .header-left { width:var(--sidebar-mini); padding:0 .55rem; }
        body.mini .logo-text { display:none; }
        body.mini #toggle-btn svg { transform:rotate(180deg); }
        body.mini .sidebar { width:var(--sidebar-mini); padding-left:.5rem; padding-right:.5rem; }
        body.mini .menu-title { opacity:0; height:.45rem; padding:0; overflow:hidden; }
        body.mini .menu-label { display:none; }
        body.mini .menu-link { justify-content:center; padding:.65rem 0; }
        body.mini .page-wrapper { margin-left:var(--sidebar-mini); }

        /* ------------------------------------------------------ শেয়ার্ড কম্পোনেন্ট */
        .card {
            background:var(--card); border:1px solid var(--line); border-radius:12px;
            padding:1rem; margin-bottom:1rem; box-shadow:var(--shadow);
        }
        .card-title { font-size:.95rem; font-weight:700; margin:0 0 .85rem; display:flex; align-items:center; gap:.5rem; }
        .card-title .muted { font-weight:400; }

        .msg { padding:.65rem .9rem; border-radius:9px; margin-bottom:.6rem; font-size:.9rem;
               display:flex; align-items:center; gap:.5rem; }
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

        .scroll { overflow-x:auto; }
        table { border-collapse:collapse; width:100%; min-width:640px; }
        th, td { padding:.6rem .65rem; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; }
        thead th {
            font-weight:600; color:var(--muted); font-size:.75rem; text-transform:uppercase;
            letter-spacing:.04em; background:var(--bg);
        }
        tbody tr:hover td { background:var(--bg); }
        tfoot td { font-weight:700; border-top:2px solid var(--line); }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        table a { color:var(--brand); font-weight:500; }

        form.filters { display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end; }
        label { display:block; font-size:.78rem; color:var(--muted); margin-bottom:.25rem; font-weight:500; }
        input, select, textarea {
            font:inherit; padding:.5rem .6rem; border:1px solid var(--line); border-radius:8px;
            background:var(--card); color:var(--fg); min-width:0;
        }
        input:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-soft);
        }
        button, .btn {
            font:inherit; font-weight:600; padding:.5rem .95rem; border:1px solid transparent;
            border-radius:8px; background:var(--brand); color:#fff; cursor:pointer;
            display:inline-flex; align-items:center; gap:.4rem;
        }
        button:hover, .btn:hover { filter:brightness(.94); }
        button.ghost, .btn.ghost { background:transparent; color:var(--fg); border-color:var(--line); }
        button.ghost:hover, .btn.ghost:hover { background:var(--bg); filter:none; }

        .muted { color:var(--muted); }
        .empty { padding:2.5rem 1rem; text-align:center; color:var(--muted); }
        .pos { color:var(--success); }
        .neg { color:var(--danger); }

        /* ------------------------------------------------------------- মোবাইল */
        #backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:19; }
        #mobile-btn { display:none; }

        @media (max-width:900px) {
            .header-left { width:auto; border-right:0; }
            #toggle-btn { display:none; }
            #mobile-btn { display:grid; }
            .sidebar { transform:translateX(-100%); width:var(--sidebar-w); }
            body.drawer .sidebar { transform:none; }
            body.drawer #backdrop { display:block; }
            .page-wrapper, body.mini .page-wrapper { margin-left:0; }
            .user-detail { display:none; }
            .content { padding:1rem .85rem; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <a class="logo" href="<?= View::e($appUrl . '/admin') ?>">
            <span class="logo-mark"><?= View::e(mb_substr((string) ($appName ?? 'E'), 0, 1)) ?></span>
            <span class="logo-text"><?= View::e($appName ?? '') ?></span>
        </a>
        <button id="toggle-btn" type="button" title="সাইডবার ছোট/বড় করুন" aria-label="সাইডবার টগল">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
        </button>
    </div>

    <button id="mobile-btn" class="nav-box" type="button" aria-label="মেনু"><?= Menu::icon('menu') ?></button>

    <ul class="user-menu">
        <li>
            <button class="nav-box" id="lang-btn" type="button" title="ভাষা">বাং</button>
        </li>
        <li>
            <button class="nav-box" id="theme-btn" type="button" title="লাইট/ডার্ক">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </li>
        <li>
            <button class="nav-box" id="fullscreen-btn" type="button" title="ফুলস্ক্রিন">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            </button>
        </li>

        <li class="main-drop" id="profile-drop">
            <button class="userset" type="button" aria-haspopup="true" aria-expanded="false">
                <span class="user-letter"><?= View::e($initial) ?></span>
                <span class="user-detail">
                    <span class="user-name"><?= View::e($user['name'] ?? '') ?></span>
                    <span class="user-role"><?= Auth::isSuperAdmin() ? 'সুপার অ্যাডমিন' : 'স্টাফ' ?></span>
                </span>
                <?= Menu::icon('chevron') ?>
            </button>

            <div class="menu-drop-user" role="menu">
                <div class="profileset">
                    <span class="user-letter"><?= View::e($initial) ?></span>
                    <span>
                        <strong style="display:block"><?= View::e($user['name'] ?? '') ?></strong>
                        <span class="muted" style="font-size:.78rem"><?= View::e($user['email'] ?? $user['phone'] ?? '') ?></span>
                    </span>
                </div>
                <div class="drop-line"></div>
                <?php foreach (Menu::profile() as $item): ?>
                    <?php if (!empty($item['danger'])): ?><div class="drop-line"></div><?php endif; ?>
                    <a class="dropdown-item <?= !empty($item['danger']) ? 'danger' : '' ?>"
                       href="<?= View::e($appUrl . $item['path']) ?>" role="menuitem">
                        <?= Menu::icon($item['icon']) ?><?= View::e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </li>
    </ul>
</header>

<aside class="sidebar" id="sidebar">
    <?php foreach (Menu::admin() as $group): ?>
        <div class="menu-group">
            <div class="menu-title"><?= View::e($group['title']) ?></div>
            <?php foreach ($group['items'] as $item): ?>
                <a class="menu-link <?= Menu::isActive($item, $current) ? 'active' : '' ?>"
                   href="<?= View::e($appUrl . $item['path']) ?>" title="<?= View::e($item['label']) ?>">
                    <?= Menu::icon($item['icon'] ?? 'grid') ?>
                    <span class="menu-label"><?= View::e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</aside>

<div id="backdrop"></div>

<div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div>
                <h1><?= View::e($title ?? '') ?></h1>
                <div class="breadcrumb">
                    <a href="<?= View::e($appUrl . '/admin') ?>">ড্যাশবোর্ড</a>
                    <?php if ($crumb !== null && $crumb['label'] !== 'ড্যাশবোর্ড'): ?>
                        &rsaquo; <?= View::e($crumb['group']) ?> &rsaquo; <?= View::e($crumb['label']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($pageActions)): ?>
                <div class="page-actions"><?= $pageActions ?></div>
            <?php endif; ?>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="msg msg-<?= View::e($message[0]) ?>"><?= View::e($message[1]) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
    </div>
</div>

<script>
// ---------------------------------------------------------------------------
// সব অ্যাডমিন পেজের শেয়ার্ড API হেল্পার।
// রেসপন্সের `m` মেসেজগুলো নিজে থেকেই দেখায়, `status !== 1` হলে null দেয়।
// ব্যবহার:  const data = await api('/products', {name: '...'}, 'POST');
// ---------------------------------------------------------------------------
window.API_BASE = '<?= View::e($appUrl) ?>/api/v1';

window.api = async function (path, body, method) {
    method = method || (body ? 'POST' : 'GET');

    let data;

    try {
        const response = await fetch(window.API_BASE + path, {
            method:      method,
            headers:     { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body:        body ? JSON.stringify(body) : undefined
        });

        if (response.status === 401) {
            location.href = '<?= View::e($appUrl) ?>/admin/login';
            return null;
        }

        data = await response.json();
    } catch (error) {
        window.toast('e', 'রিকোয়েস্ট পাঠানো যায়নি: ' + error.message);
        return null;
    }

    (data.m || []).forEach(function (m) { window.toast(m[0], m[1]); });

    return data.status === 1 ? data : null;
};

// স্ক্রিনের ডান-নিচে ভাসমান মেসেজ
window.toast = function (type, text) {
    let box = document.getElementById('toast-box');

    if (!box) {
        box = document.createElement('div');
        box.id = 'toast-box';
        box.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:60;display:flex;'
                          + 'flex-direction:column;gap:.5rem;max-width:min(360px,90vw)';
        document.body.appendChild(box);
    }

    const item = document.createElement('div');
    item.className = 'msg msg-' + type;
    item.style.boxShadow = 'var(--shadow)';
    item.textContent = text;
    box.appendChild(item);

    setTimeout(function () { item.remove(); }, 5000);
};

(function () {
    var body = document.body;

    // ---- সাইডবার কলাপস (ডেস্কটপ), পছন্দ localStorage এ মনে থাকে
    if (localStorage.getItem('sidebar') === 'mini') {
        body.classList.add('mini');
    }

    document.getElementById('toggle-btn').addEventListener('click', function () {
        body.classList.toggle('mini');
        localStorage.setItem('sidebar', body.classList.contains('mini') ? 'mini' : 'full');
    });

    // ---- মোবাইল ড্রয়ার
    var backdrop = document.getElementById('backdrop');
    document.getElementById('mobile-btn').addEventListener('click', function () {
        body.classList.toggle('drawer');
    });
    backdrop.addEventListener('click', function () { body.classList.remove('drawer'); });

    // ---- প্রোফাইল ড্রপডাউন
    var drop = document.getElementById('profile-drop');
    var trigger = drop.querySelector('.userset');

    trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        drop.classList.toggle('open');
        trigger.setAttribute('aria-expanded', drop.classList.contains('open'));
    });

    document.addEventListener('click', function () {
        drop.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            drop.classList.remove('open');
            body.classList.remove('drawer');
        }
    });

    // ---- থিম টগল (সিস্টেম → লাইট → ডার্ক)
    var saved = localStorage.getItem('theme');
    if (saved) { document.documentElement.setAttribute('data-theme', saved); }

    document.getElementById('theme-btn').addEventListener('click', function () {
        var now = document.documentElement.getAttribute('data-theme');
        var next = now === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
    });

    // ---- ফুলস্ক্রিন
    document.getElementById('fullscreen-btn').addEventListener('click', function () {
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else { document.documentElement.requestFullscreen(); }
    });

    // ---- ভাষা (ফেজ ৭-এ আসল ইমপ্লিমেন্টেশন হবে)
    document.getElementById('lang-btn').addEventListener('click', function () {
        alert('ভাষা টগল ফেজ ৭-এ যুক্ত হবে।');
    });
})();
</script>
</body>
</html>
